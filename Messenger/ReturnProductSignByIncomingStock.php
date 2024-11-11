<?php
/*
 *  Copyright 2024.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Products\Sign\Messenger;

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Repository\ProductSignByOrder\ProductSignByOrderInterface;
use BaksDev\Products\Sign\Type\Event\ProductSignEventUid;
use BaksDev\Products\Sign\UseCase\Admin\Status\ProductSignCancelDTO;
use BaksDev\Products\Sign\UseCase\Admin\Status\ProductSignStatusHandler;
use BaksDev\Products\Stocks\Entity\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Entity\ProductStockTotal;
use BaksDev\Products\Stocks\Messenger\ProductStockMessage;
use BaksDev\Products\Stocks\Repository\ProductStocksById\ProductStocksByIdInterface;
use BaksDev\Products\Stocks\Repository\ProductStocksTotalStorage\ProductStocksTotalStorageInterface;
use BaksDev\Products\Stocks\Repository\UpdateProductStock\AddProductStockInterface;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\Collection\ProductStockStatusCollection;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusIncoming;
use BaksDev\Products\Stocks\UseCase\Admin\Package\PackageProductStockDTO;
use BaksDev\Users\Profile\UserProfile\Repository\UserByUserProfile\UserByUserProfileInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 0)]
final class ReturnProductSignByIncomingStock
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DeduplicatorInterface $deduplicator,
        private readonly ProductSignByOrderInterface $productSignByOrder,
        private readonly ProductSignStatusHandler $productSignStatusHandler,
        LoggerInterface $productsSignLogger,
    ) {
        $this->logger = $productsSignLogger;
    }

    /**
     * Отменить (вернуть в оборот) «Честный знак» при возврате заказа
     */
    public function __invoke(ProductStockMessage $message): void
    {
        /** Получаем статус заявки */
        $ProductStockEvent = $this->entityManager
            ->getRepository(ProductStockEvent::class)
            ->find($message->getEvent());

        if(!$ProductStockEvent)
        {
            return;
        }

        $this->entityManager->clear();

        $OrderUid = $ProductStockEvent->getOrder();

        if(false === ($OrderUid instanceof OrderUid))
        {
            return;
        }

        /** Если Статус заявки не является Incoming «Приход на склад» */
        if(false === $ProductStockEvent->getStatus()->equals(ProductStockStatusIncoming::class))
        {
            return;
        }

        /** Идентификатор профиля склада при поступлении */
        $UserProfileUid = $ProductStockEvent->getProfile();

        /** Получаем все знаки по идентификатору заказа со статусом Done «Выполнен» */
        $sign = $this
            ->productSignByOrder
            ->forOrder($OrderUid)
            ->withStatusDone()
            ->findAll();

        if(false === $sign)
        {
            return;
        }

        $Deduplicator = $this->deduplicator
            ->namespace('products-stocks')
            ->deduplication([
                (string) $message->getId(),
                ProductStockStatusIncoming::STATUS,
                md5(self::class)
            ]);

        if($Deduplicator->isExecuted())
        {
            return;
        }

        foreach($sign as $code)
        {
            $ProductSignCancelDTO = new ProductSignCancelDTO($UserProfileUid);
            $ProductSignCancelDTO->setId(new ProductSignEventUid($code['code_event']));
            $handle = $this->productSignStatusHandler->handle($ProductSignCancelDTO);

            if($handle instanceof ProductSign)
            {
                $this->logger->info(
                    sprintf('%s: Ошибка при отмене «Честного знака» при возврате продукции по заказу %s', $handle, $ProductStockEvent->getNumber()),
                    [self::class.':'.__LINE__]
                );
            }
        }

        $Deduplicator->save();

        $this->logger->info(
            sprintf('%s: Отменили «Честные знаки» при возврате продукции по заказу', $ProductStockEvent->getNumber()),
            [self::class.':'.__LINE__]
        );

    }
}
