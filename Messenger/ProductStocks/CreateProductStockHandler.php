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

namespace BaksDev\Products\Sign\Messenger\ProductStocks;

use BaksDev\Core\Cache\AppCacheInterface;
use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Sign\Entity\Event\ProductSignEvent;
use BaksDev\Products\Sign\Repository\CurrentEvent\ProductSignCurrentEventInterface;
use BaksDev\Products\Sign\UseCase\Admin\NewEdit\ProductSignDTO;
use BaksDev\Products\Stocks\Entity\ProductStock;
use BaksDev\Products\Stocks\Repository\CurrentProductStocks\CurrentProductStocksInterface;
use BaksDev\Products\Stocks\UseCase\Admin\Purchase\Products\ProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Purchase\PurchaseProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Purchase\PurchaseProductStockHandler;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CreateProductStockHandler
{
    private ProductSignCurrentEventInterface $productSignCurrentEvent;

    private LoggerInterface $logger;

    private PurchaseProductStockHandler $purchaseProductStockHandler;

    public function __construct(
        ProductSignCurrentEventInterface $productSignCurrentEvent,
        PurchaseProductStockHandler $purchaseProductStockHandler,
        LoggerInterface $productsSignLogger
    )
    {
        $this->productSignCurrentEvent = $productSignCurrentEvent;
        $this->logger = $productsSignLogger;
        $this->purchaseProductStockHandler = $purchaseProductStockHandler;
    }

    public function __invoke(CreateProductStockMessage $message): void
    {
        $ProductSignEvent = $this->productSignCurrentEvent->findProductSignEvent($message->getId());

        if(!$ProductSignEvent)
        {
            return;
        }

        /** @var ProductSignDTO $ProductSignDTO */
        $ProductSignDTO = new ProductSignDTO($ProductSignEvent->getProfile());
        $ProductSignEvent->getDto($ProductSignDTO);
        $ProductSignCodeDTO = $ProductSignDTO->getCode();

        $PurchaseProductStockDTO = new PurchaseProductStockDTO($ProductSignDTO->getProfile());
        $PurchaseNumber = number_format(microtime(true) * 100, 0, '.', '.');
        $PurchaseProductStockDTO->setNumber($PurchaseNumber);

        $ProductStockDTO = new ProductStockDTO();
        $ProductStockDTO->setProduct($ProductSignCodeDTO->getProduct());
        $ProductStockDTO->setOffer($ProductSignCodeDTO->getOffer());
        $ProductStockDTO->setVariation($ProductSignCodeDTO->getVariation());
        $ProductStockDTO->setModification($ProductSignCodeDTO->getModification());
        $ProductStockDTO->setTotal(1);

        $PurchaseProductStockDTO->addProduct($ProductStockDTO);

        $handle = $this->purchaseProductStockHandler->handle($PurchaseProductStockDTO);

        if($handle instanceof ProductStock)
        {
            $this->logger->info('Создали лист закупки продукции', [
                __FILE__.':'.__LINE__,
                'ProductSignUid' => $message->getId()
            ]);

            return;
        }

        $this->logger->critical('Ошибка при создании листа закупки продукции', [
            __FILE__.':'.__LINE__,
            'ProductSignUid' => $message->getId()
        ]);

    }
}
