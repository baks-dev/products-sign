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

namespace BaksDev\Products\Sign\Messenger\ProductSignStatus;

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Messenger\OrderMessage;
use BaksDev\Orders\Order\Repository\OrderEvent\OrderEventInterface;
use BaksDev\Orders\Order\Type\Status\OrderStatus\Collection\OrderStatusCanceled;
use BaksDev\Products\Sign\Messenger\ProductSignStatus\ProductSignCancel\ProductSignCancelMessage;
use BaksDev\Products\Sign\Repository\ProductSignProcessByOrder\ProductSignProcessByOrderInterface;
use BaksDev\Products\Sign\Type\Event\ProductSignEventUid;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Если статус заказа Canceled «Отменен» - снимаем резерв и применяем статус «Честного знака» на статус New «Новый»
 * Если заказ был выполнен - следовательно это возврат @see ProductSignReturnByOrderReturnDispatcher
 */
#[Autoconfigure(public: true)]
#[AsMessageHandler(priority: 80)]
final readonly class ProductSignCancelByOrderCanceledDispatcher
{
    public function __construct(
        #[Target('productsSignLogger')] private LoggerInterface $logger,
        private OrderEventInterface $OrderEventRepository,
        private ProductSignProcessByOrderInterface $productSignProcessByOrder,
        private MessageDispatchInterface $MessageDispatch,
    ) {}

    public function __invoke(OrderMessage $message): void
    {
        $OrderEvent = $this->OrderEventRepository
            ->find($message->getEvent());

        if(false === ($OrderEvent instanceof OrderEvent))
        {
            $this->logger->critical('products-sign: Не найдено событие Order',
                [var_export($message, true), self::class.':'.__LINE__]);

            return;
        }

        /**
         * Если статус не Canceled «Отмена» - завершаем обработчик
         */
        if(false === $OrderEvent->isStatusEquals(OrderStatusCanceled::class))
        {
            return;
        }

        $ProductSignEvents = $this->productSignProcessByOrder
            ->forOrder($message->getId())
            ->findAll();

        if(false === $ProductSignEvents || $ProductSignEvents->valid() === false)
        {
            $this->logger->warning
            (
                sprintf('%s: Честные знаки для отмены заказа не найдены', $OrderEvent->getOrderNumber()),
                [var_export($message, true), self::class.':'.__LINE__],
            );

            return;
        }

        /** @var ProductSignEventUid $event */
        foreach($ProductSignEvents as $ProductSignEventUid)
        {
            $ProductSignCancelMessage = new ProductSignCancelMessage(
                $OrderEvent->getOrderProfile(),
                $ProductSignEventUid,
            );

            $this->MessageDispatch->dispatch(
                message: $ProductSignCancelMessage,
                transport: 'products-sign',
            );
        }
    }
}
