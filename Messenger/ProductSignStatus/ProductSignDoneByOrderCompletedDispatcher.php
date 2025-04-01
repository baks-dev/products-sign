<?php
/*
 *  Copyright 2025.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Products\Sign\Messenger\ProductSignStatus;

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Entity\Products\OrderProduct;
use BaksDev\Orders\Order\Messenger\OrderMessage;
use BaksDev\Orders\Order\Repository\OrderEvent\OrderEventInterface;
use BaksDev\Orders\Order\Type\Status\OrderStatus\OrderStatusCompleted;
use BaksDev\Products\Product\Repository\CurrentProductByArticle\CurrentProductDTO;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierInterface;
use BaksDev\Products\Sign\Messenger\ProductSignStatus\ProductSignDone\ProductSignDoneMessage;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusDone;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Делаем отметку Честный знак на продукцию Done «Выполнен» если статус заказа Completed «Выполнен»
 */
#[AsMessageHandler(priority: 10)]
final readonly class ProductSignDoneByOrderCompletedDispatcher
{
    public function __construct(
        #[Target('productsSignLogger')] private LoggerInterface $logger,
        private OrderEventInterface $OrderEventRepository,
        private DeduplicatorInterface $deduplicator,
        private CurrentProductIdentifierInterface $CurrentProductIdentifier,
        private MessageDispatchInterface $MessageDispatch
    ) {}


    public function __invoke(OrderMessage $message): void
    {
        $Deduplicator = $this->deduplicator
            ->namespace('products-sign')
            ->deduplication([
                (string) $message->getId(),
                ProductSignStatusDone::STATUS,
                self::class
            ]);

        if($Deduplicator->isExecuted())
        {
            return;
        }

        /** Получаем событие заказа */
        $OrderEvent = $this->OrderEventRepository->find($message->getEvent());

        if(false === ($OrderEvent instanceof OrderEvent))
        {
            $this->logger->critical(
                'products-sign: Не найдено событие Order',
                [var_export($message, true), self::class.':'.__LINE__]
            );

            return;
        }

        /**
         * Если статус не Completed «Выполнен» - завершаем обработчик
         */
        if(false === $OrderEvent->isStatusEquals(OrderStatusCompleted::class))
        {
            return;
        }

        /** @var OrderProduct $product */
        foreach($OrderEvent->getProduct() as $product)
        {
            /**
             * Получаем константы продукции по идентификаторам
             */

            $CurrentProductDTO = $this->CurrentProductIdentifier
                ->forEvent($product->getProduct())
                ->forOffer($product->getOffer())
                ->forVariation($product->getVariation())
                ->forModification($product->getModification())
                ->find();

            if(false === ($CurrentProductDTO instanceof CurrentProductDTO))
            {
                $this->logger->critical(
                    'products-sign: Продукт не найден',
                    [var_export($CurrentProductDTO, true), self::class.':'.__LINE__]
                );

                continue;
            }

            /**
             * Отмечаем честный знак о выполнении
             */

            $ProductSignDoneMessage = new ProductSignDoneMessage(
                $message->getId(),
                $CurrentProductDTO->getProduct(),
                $CurrentProductDTO->getOfferConst(),
                $CurrentProductDTO->getVariationConst(),
                $CurrentProductDTO->getModificationConst(),
            );

            $productTotal = $product->getTotal();

            for($i = 1; $i <= $productTotal; $i++)
            {
                $this->MessageDispatch->dispatch(
                    message: $ProductSignDoneMessage,
                    transport: 'products-sign'
                );
            }
        }

        $Deduplicator->save();

    }
}
