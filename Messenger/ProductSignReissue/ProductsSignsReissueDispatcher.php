<?php
/*
 * Copyright 2026.  Baks.dev <admin@baks.dev>
 *
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is furnished
 *  to do so, subject to the following conditions:
 *
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 *  THE SOFTWARE.
 */

declare(strict_types=1);

namespace BaksDev\Products\Sign\Messenger\ProductSignReissue;

use BaksDev\Core\Messenger\MessageDelay;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Repository\CurrentOrderEvent\CurrentOrderEventInterface;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierByEventInterface;
use BaksDev\Products\Sign\Messenger\ProductSignStatus\ProductSignCancel\ProductSignCancelMessage;
use BaksDev\Products\Sign\Messenger\ProductSignStatus\ProductSignProcess\ProductSignProcessMessage;
use BaksDev\Products\Sign\Repository\ProductSignProcessByOrder\ProductSignProcessByOrderInterface;
use BaksDev\Products\Sign\Type\Id\ProductSignUid;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 0)]
final readonly class ProductsSignsReissueDispatcher
{
    public function __construct(
        #[Target('productsSignLogger')] private LoggerInterface $Logger,
        private MessageDispatchInterface $MessageDispatch,
        private CurrentOrderEventInterface $CurrentOrderEventRepository,
        private ProductSignProcessByOrderInterface $ProductSignProcessByOrderRepository,
        private CurrentProductIdentifierByEventInterface $CurrentProductIdentifierByEventRepository,
    ) {}

    /** Перевыпуск честных знаков на продукцию */
    public function __invoke(ProductsSignsReissueMessage $message): void
    {
        /** Находим событие заказа */
        $orderEvent = $this->CurrentOrderEventRepository
            ->forOrder($message->getOrder())
            ->find();

        if(false === ($orderEvent instanceof OrderEvent))
        {
            $this->Logger->critical(sprintf('Событие заказа %s не было найдено', $message->getOrder()));
            return;
        }


        /**
         * Получаем все честные знаки для данного заказа
         */

        $signs = $this->ProductSignProcessByOrderRepository
            ->forOrder($orderEvent->getMain())
            ->findAll();

        foreach($signs as $productSignEventUid)
        {
            /** Отменяем честный знак */
            $this->MessageDispatch->dispatch(
                message: new ProductSignCancelMessage(
                    $message->getProfile(),
                    $productSignEventUid,
                ),
                transport: 'products-sign',
            );
        }


        /**
         * Отправляем запросы на повторное резервирование
         */

        foreach($orderEvent->getProduct() as $orderProduct)
        {
            /** Получаем текущие идентификаторы */
            $currentProductIdentifierResult = $this->CurrentProductIdentifierByEventRepository
                ->forEvent($orderProduct->getProduct())
                ->forOffer($orderProduct->getOffer())
                ->forVariation($orderProduct->getVariation())
                ->forModification($orderProduct->getModification())
                ->find();

            $productSignPart = new ProductSignUid();

            $productSignProcessMessage = new ProductSignProcessMessage(
                order: $orderEvent->getMain(),
                part: $productSignPart,
                user: $message->getUser(),
                profile: $message->getProfile(),
                product: $currentProductIdentifierResult->getProduct(),
                offer: $currentProductIdentifierResult->getOfferConst(),
                variation: $currentProductIdentifierResult->getVariationConst(),
                modification: $currentProductIdentifierResult->getModificationConst(),
            );

            $productTotal = $orderProduct->getTotal();

            for($i = 1; $i <= $productTotal; $i++)
            {
                $productSignProcessMessage->setPart($productSignPart);

                $this->MessageDispatch
                    ->dispatch(
                        message: $productSignProcessMessage,
                        stamps: [new MessageDelay('5 seconds')],
                        transport: 'products-sign',
                    );

                /** Разбиваем по 100 шт */
                if(($i % 100) === 0)
                {
                    $productSignPart = new ProductSignUid();
                }
            }
        }
    }
}