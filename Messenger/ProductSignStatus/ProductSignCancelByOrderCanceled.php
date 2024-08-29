<?php
/*
 *  Copyright 2023.  Baks.dev <admin@baks.dev>
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
use BaksDev\Orders\Order\Entity\Products\OrderProduct;
use BaksDev\Orders\Order\Messenger\OrderMessage;
use BaksDev\Orders\Order\Repository\OrderEvent\OrderEventInterface;
use BaksDev\Orders\Order\Type\Status\OrderStatus\OrderStatusCanceled;
use BaksDev\Products\Product\Repository\ProductModificationConst\ProductModificationConstInterface;
use BaksDev\Products\Product\Repository\ProductOfferConst\ProductOfferConstInterface;
use BaksDev\Products\Product\Repository\ProductVariationConst\ProductVariationConstInterface;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Repository\ProductSignProcessByOrder\ProductSignProcessByOrderInterface;
use BaksDev\Products\Sign\Repository\ProductSignProcessByOrderProduct\ProductSignProcessByOrderProductInterface;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusCancel;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusDone;
use BaksDev\Products\Sign\UseCase\Admin\Status\ProductSignCancelDTO;
use BaksDev\Products\Sign\UseCase\Admin\Status\ProductSignDoneDTO;
use BaksDev\Products\Sign\UseCase\Admin\Status\ProductSignStatusHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ProductSignCancelByOrderCanceled
{
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly ProductSignStatusHandler $productSignStatusHandler,
        private readonly OrderEventInterface $orderEventRepository,
        private readonly ProductSignProcessByOrderInterface $productSignProcessByOrder,
        private readonly DeduplicatorInterface $deduplicator,
        LoggerInterface $productsSignLogger,
    ) {
        $this->logger = $productsSignLogger;
    }


    /**
     * Делаем отмену Честный знак на New «Новый» если статус заказа Canceled «Отменен»
     */
    public function __invoke(OrderMessage $message): void
    {


        /** Log Data */
        $dataLogs['OrderUid'] = (string) $message->getId();
        $dataLogs['OrderEventUid'] = (string) $message->getEvent();
        $dataLogs['LastOrderEventUid'] = (string) $message->getLast();

        $OrderEvent = $this->orderEventRepository->find($message->getEvent());

        if(false === $OrderEvent)
        {
            $dataLogs[0] = self::class.':'.__LINE__;
            $this->logger->critical('products-sign: Не найдено событие Order', $dataLogs);

            return;
        }

        /**
         * Если статус не Canceled «Отмена» - завершаем обработчик
         */
        if(false === $OrderEvent->isStatusEquals(OrderStatusCanceled::class))
        {
            return;
        }

        $Deduplicator = $this->deduplicator
            ->namespace('products-sign')
            ->deduplication([
                (string) $message->getId(),
                ProductSignStatusCancel::STATUS,
                md5(self::class)
            ]);

        if($Deduplicator->isExecuted())
        {
            return;
        }

        $this->logger->info('Делаем поиск и отмену всех «Честных знаков» при отмене заказа:');

        $ProductSignEvents = $this->productSignProcessByOrder->findByOrder($message->getId());

        foreach($ProductSignEvents as $event)
        {
            $ProductSignCancelDTO = new ProductSignCancelDTO($event->getProfile());
            $event->getDto($ProductSignCancelDTO);
            $this->productSignStatusHandler->handle($ProductSignCancelDTO);

            $this->logger->warning(
                'Отменили «Честный знак» (возвращаем статус New «Новый»)',
                [
                    self::class.':'.__LINE__,
                    'ProductSignUid' => $event->getMain()
                ]
            );
        }

        $Deduplicator->save();
    }
}
