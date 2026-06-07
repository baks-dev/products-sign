<?php
/*
 *  Copyright 2026.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Products\Sign\Messenger\OrderProductSign;

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Repository\CurrentOrderEvent\CurrentOrderEventInterface;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Ozon\Orders\Api\Exemplar\UpdateOzonOrdersExemplarRequest;
use BaksDev\Ozon\Orders\Api\GetOzonOrderInfoRequest;
use BaksDev\Ozon\Orders\BaksDevOzonOrdersBundle;
use BaksDev\Ozon\Orders\Type\DeliveryType\TypeDeliveryFbsOzon;
use BaksDev\Ozon\Orders\UseCase\New\NewOzonOrderDTO;
use BaksDev\Ozon\Type\Id\OzonTokenUid;
use BaksDev\Products\Sign\Entity\Event\ProductSignEvent;
use BaksDev\Products\Sign\Messenger\ProductSignMessage;
use BaksDev\Products\Sign\Repository\CurrentEvent\ProductSignCurrentEventInterface;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusProcess;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[Autoconfigure(shared: false)]
#[AsMessageHandler(priority: 0)]
final class SubmitOzonOrderProductSignDispatcher
{
    public function __construct(
        #[Target('productsSignLogger')] private LoggerInterface $Logger,
        private DeduplicatorInterface $deduplicator,
        private readonly ProductSignCurrentEventInterface $ProductSignCurrentEventRepository,
        private readonly CurrentOrderEventInterface $CurrentOrderEventRepository,

        private readonly ?GetOzonOrderInfoRequest $GetOzonOrderInfoRequest = null,
        private readonly ?UpdateOzonOrdersExemplarRequest $UpdateOzonOrdersExemplarRequest = null,
    ) {}

    public function __invoke(ProductSignMessage $message): void
    {
        /** Дедубликатор по идентификатору честного знака */
        $Deduplicator = $this->deduplicator
            ->namespace('ozon-orders')
            ->deduplication([
                (string) $message->getId(),
                self::class,
            ]);

        if($Deduplicator->isExecuted() === true)
        {
            return;
        }

        if(false === class_exists(BaksDevOzonOrdersBundle::class))
        {
            $Deduplicator->save();
            return;
        }

        /** Получаем текущее состояние честного знака */

        $ProductSignEvent = $this->ProductSignCurrentEventRepository
            ->forProductSign($message->getId())
            ->find();

        if(false === ($ProductSignEvent instanceof ProductSignEvent))
        {
            $this->Logger->critical(
                message: 'ozon-orders: Не найдено событие ProductSignEvent',
                context: [self::class.':'.__LINE__, var_export($message, true)],
            );
            return;
        }

        if(false === $ProductSignEvent->isStatusEquals(ProductSignStatusProcess::class))
        {
            return;
        }

        if(false === ($ProductSignEvent->getOrderId() instanceof OrderUid))
        {
            return;
        }

        /** Получаем текущее состояние заказа */

        $OrderEvent = $this->CurrentOrderEventRepository
            ->forOrder($ProductSignEvent->getOrderId())
            ->find();

        if(false === ($OrderEvent instanceof OrderEvent))
        {
            $this->Logger->critical(
                message: 'ozon-orders: Не найдено событие OrderEvent',
                context: [self::class.':'.__LINE__, var_export($message, true)],
            );

            return;
        }

        /**
         * Если тип доставки заказа НЕ Ozon Fbs «Доставка службой Ozon» - Завершаем обработчик
         */
        if(false === $OrderEvent->isDeliveryTypeEquals(TypeDeliveryFbsOzon::TYPE))
        {
            $Deduplicator->save();
            return;
        }

        if(empty($OrderEvent->getPostingNumber()))
        {
            $this->Logger->critical(
                message: 'ozon-orders: Не найдено идентификатора отправления для передачи маркировки честного знака',
                context: [$OrderEvent->getMain(), self::class.':'.__LINE__],
            );

            return;
        }

        /** Получаем информацию о заказе в селлере  */

        $OzonTokenUid = new OzonTokenUid($OrderEvent->getOrderTokenIdentifier());

        $NewOzonOrderDTO = $this->GetOzonOrderInfoRequest
            ->forTokenIdentifier($OzonTokenUid)
            ->find($OrderEvent->getPostingNumber());

        /** Пропускаем если отсутствует информация о заказе */
        if(false === $NewOzonOrderDTO instanceof NewOzonOrderDTO)
        {
            $this->Logger->critical(
                sprintf('products-sign: Не удалось получить информацию о заказе %s', $OrderEvent->getPostingNumber()),
                [self::class.':'.__LINE__],
            );

            return;
        }

        foreach($NewOzonOrderDTO->getProduct() as $NewOzonOrderProductDTO)
        {
            /** Пропускаем если в заказе отсутствует информация об отправлениях */
            if(true === empty($NewOzonOrderProductDTO->getExemplar()))
            {
                $this->Logger->warning(
                    sprintf('%s: В заказе отсутствует информация об отправлениях требующих обязательной маркировки', $OrderEvent->getPostingNumber()),
                    [self::class.':'.__LINE__],
                );

                continue;
            }

            /** Преобразуем код маркировки к требуемому формату */
            $subChar = "";
            preg_match_all('/\((\d{2})\)((?:(?!\(\d{2}\)).)*)/', $ProductSignEvent->getCode(), $matches, PREG_SET_ORDER);
            $code = $matches[0][1].$matches[0][2].$matches[1][1].$matches[1][2].$subChar.$matches[2][1].$matches[2][2].$subChar.$matches[3][1].$matches[3][2];

            /** Обновляем информацию о честном знаке */
            $isUpdate = $this->UpdateOzonOrdersExemplarRequest
                ->forTokenIdentifier($OzonTokenUid)
                ->posting($NewOzonOrderDTO->getPostingNumber())
                ->product($NewOzonOrderProductDTO->getSku())
                ->exemplar($NewOzonOrderProductDTO->getExemplar())
                ->sign($code)
                ->update();

            if(false === $isUpdate)
            {
                $this->Logger->critical(
                    'ozon-orders: Не удалось обновить информацию о честном знаке',
                    [self::class.':'.__LINE__, $OrderEvent->getPostingNumber(), var_export($message, true)],
                );

                continue;
            }

            $this->Logger->info(
                sprintf('%s: обновили информацию о честном знаке', $OrderEvent->getPostingNumber()),
                [self::class.':'.__LINE__, var_export($message, true)],
            );
        }


        $Deduplicator->save();
    }
}
