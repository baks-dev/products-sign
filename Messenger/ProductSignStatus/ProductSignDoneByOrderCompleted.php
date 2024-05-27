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

use BaksDev\Orders\Order\Entity\Products\OrderProduct;
use BaksDev\Orders\Order\Messenger\OrderMessage;
use BaksDev\Orders\Order\Repository\OrderEvent\OrderEventInterface;
use BaksDev\Orders\Order\Type\Status\OrderStatus\OrderStatusCompleted;
use BaksDev\Products\Product\Repository\ProductModificationConst\ProductModificationConstInterface;
use BaksDev\Products\Product\Repository\ProductOfferConst\ProductOfferConstInterface;
use BaksDev\Products\Product\Repository\ProductVariationConst\ProductVariationConstInterface;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Repository\ProductSignProcessByOrder\ProductSignProcessByOrderInterface;
use BaksDev\Products\Sign\Repository\ProductSignProcessByOrderProduct\ProductSignProcessByOrderProductInterface;
use BaksDev\Products\Sign\UseCase\Admin\Status\ProductSignCancelDTO;
use BaksDev\Products\Sign\UseCase\Admin\Status\ProductSignDoneDTO;
use BaksDev\Products\Sign\UseCase\Admin\Status\ProductSignStatusHandler;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ProductSignDoneByOrderCompleted
{
    private LoggerInterface $logger;
    private OrderEventInterface $orderEventRepository;
    private ProductSignProcessByOrderProductInterface $productSignProcessByOrderProduct;
    private ProductOfferConstInterface $productOfferConst;
    private ProductVariationConstInterface $productVariationConst;
    private ProductModificationConstInterface $productModificationConst;
    private ProductSignStatusHandler $productSignStatusHandler;
    private ProductSignProcessByOrderInterface $productSignProcessByOrder;


    public function __construct(
        ProductOfferConstInterface $productOfferConst,
        ProductVariationConstInterface $productVariationConst,
        ProductModificationConstInterface $productModificationConst,
        ProductSignStatusHandler $productSignStatusHandler,
        OrderEventInterface $orderEventRepository,
        LoggerInterface $productsSignLogger,
        ProductSignProcessByOrderProductInterface $productSignProcessByOrderProduct,
        ProductSignProcessByOrderInterface $productSignProcessByOrder
    )
    {
        $this->logger = $productsSignLogger;
        $this->orderEventRepository = $orderEventRepository;
        $this->productSignProcessByOrderProduct = $productSignProcessByOrderProduct;
        $this->productOfferConst = $productOfferConst;
        $this->productVariationConst = $productVariationConst;
        $this->productModificationConst = $productModificationConst;
        $this->productSignStatusHandler = $productSignStatusHandler;
        $this->productSignProcessByOrder = $productSignProcessByOrder;
    }


    /**
     * Делаем отметку Честный знак Done «Выполнен» если статус заказа Completed «Выполнен»
     */
    public function __invoke(OrderMessage $message): void
    {

        $OrderEvent = $this->orderEventRepository->findByEventId($message->getEvent());

        /**
         * Если статус не Completed «Выполнен» - завершаем обработчик
         */
        if(!$OrderEvent || !$OrderEvent->isStatusEquals(OrderStatusCompleted::class))
        {
            return;
        }

        $this->logger->info('Делаем отметку Честный знак Done «Выполнен»');

        /** @var OrderProduct $product */
        foreach($OrderEvent->getProduct() as $product)
        {
            /**
             * Получаем константы продукции
             */
            $ProductOfferUid = $product->getOffer() ? $this->productOfferConst->getConst($product->getOffer()) : null;
            $ProductVariationUid = $product->getVariation() ? $this->productVariationConst->getConst($product->getVariation()) : null;
            $ProductModificationUid = $product->getModification() ? $this->productModificationConst->getConst($product->getModification()) : null;

            /**
             * Чекаем честный знак о выполнении
             */
            $total = $product->getTotal();

            for($i = 1; $i <= $total; $i++)
            {
                $ProductSignEvent = $this->productSignProcessByOrderProduct->getProductSign(
                    $message->getId(),
                    $ProductOfferUid,
                    $ProductVariationUid,
                    $ProductModificationUid
                );

                if($ProductSignEvent)
                {
                    $ProductSignDoneDTO = new ProductSignDoneDTO();
                    $ProductSignEvent->getDto($ProductSignDoneDTO);

                    $handle = $this->productSignStatusHandler->handle($ProductSignDoneDTO);

                    if(!$handle instanceof ProductSign)
                    {
                        $this->logger->critical(
                            sprintf('%s: Ошибка при обновлении статуса честного знака', $handle),
                            [
                                __FILE__.':'.__LINE__,
                                'ProductSignEventUid' => $ProductSignDoneDTO->getEvent()
                            ]
                        );

                        throw new InvalidArgumentException('Ошибка при обновлении статуса честного знака');
                    }

                    $this->logger->info('Отметили Честный знак Done «Выполнен»',
                        [
                            __FILE__.':'.__LINE__,
                            'ProductSignUid' => $ProductSignEvent->getMain()
                        ]
                    );
                }
            }
        }

        /**
         * Если по заказу остались Честный знак в статусе Process «В процессе» - делаем отмену (присваиваем статус New «Новый»)
         * @note ситуация если количество в заказе изменилось на меньшее количество
         */

        $ProductSignEvents = $this->productSignProcessByOrder->findByOrder($message->getId());

        foreach($ProductSignEvents as $event)
        {
            $ProductSignCancelDTO = new ProductSignCancelDTO($event->getProfile());
            $event->getDto($ProductSignCancelDTO);
            $this->productSignStatusHandler->handle($ProductSignCancelDTO);

            $this->logger->warning('Отменили Честный знак (возвращаем статус New «Новый»)',
                [
                    __FILE__.':'.__LINE__,
                    'ProductSignUid' => $event->getMain()
                ]
            );
        }
    }
}