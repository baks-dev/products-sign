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

use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Repository\ProductSignNew\ProductSignNewInterface;
use BaksDev\Products\Sign\UseCase\Admin\Status\ProductSignProcessDTO;
use BaksDev\Products\Sign\UseCase\Admin\Status\ProductSignStatusHandler;
use BaksDev\Products\Stocks\Entity\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Messenger\ProductStockMessage;
use BaksDev\Products\Stocks\Repository\CurrentProductStocks\CurrentProductStocksInterface;
use BaksDev\Products\Stocks\Repository\ProductStocksById\ProductStocksByIdInterface;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusIncoming;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusPackage;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ProductSignProcessByProductStocksPackage
{
    private ProductStocksByIdInterface $productStocks;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private CurrentProductStocksInterface $currentProductStocks;
    private ProductSignStatusHandler $productSignStatusHandler;
    private ProductSignNewInterface $productSignNew;

    public function __construct(

        ProductStocksByIdInterface $productStocks,
        EntityManagerInterface $entityManager,
        LoggerInterface $productsSignLogger,
        CurrentProductStocksInterface $currentProductStocks,
        ProductSignStatusHandler $productSignStatusHandler,
        ProductSignNewInterface $productSignNew
    )
    {
        $this->productStocks = $productStocks;
        $this->entityManager = $entityManager;
        $this->logger = $productsSignLogger;
        $this->currentProductStocks = $currentProductStocks;
        $this->productSignStatusHandler = $productSignStatusHandler;
        $this->productSignNew = $productSignNew;
    }

    /**
     * При статусе складской заявки Package «Упаковка» резервируем честный знак в статус Process «В процессе»
     */
    public function __invoke(ProductStockMessage $message): void
    {
        $ProductStockEvent = $this->currentProductStocks->getCurrentEvent($message->getId());

        if(!$ProductStockEvent)
        {
            return;
        }

        if($ProductStockEvent->getStatus()->equals(ProductStockStatusPackage::class) === false)
        {
            $this->logger
                ->notice('Не резервируем честный знак: Складская заявка не является Package «Упаковка»',
                    [__FILE__.':'.__LINE__, [$message->getId(), $message->getEvent(), $message->getLast()]]);
            return;
        }

        if(!$ProductStockEvent->getOrder())
        {
            $this->logger
                ->notice('Не резервируем честный знак: упаковка без идентификатора заказа',
                    [__FILE__.':'.__LINE__, [$message->getId(), $message->getEvent(), $message->getLast()]]);
            return;
        }


        if($message->getLast())
        {
            $lastProductStockEvent = $this->entityManager->getRepository(ProductStockEvent::class)->find($message->getLast());

            if($lastProductStockEvent === null || $lastProductStockEvent->getStatus()->equals(new ProductStockStatusIncoming()) === true)
            {
                $this->logger
                    ->notice('Не резервируем честный знак: Складская заявка при поступлении на склад по заказу (резерв уже имеется)',
                        [__FILE__.':'.__LINE__, [$message->getId(), $message->getEvent(), $message->getLast()]]);

                return;
            }
        }

        // Получаем всю продукцию в ордере со статусом Package (УПАКОВКА)
        $products = $this->productStocks->getProductsPackageStocks($message->getId());

        if(empty($products))
        {
            $this->logger
                ->warning('Заявка на упаковку не имеет продукции в коллекции',
                    [__FILE__.':'.__LINE__]);
            return;
        }


        $this->logger
            ->info('Добавляем резерв кода Честный знак статус Process «В процессе»',
                [__FILE__.':'.__LINE__, 'ProductStockUid' => $message->getId()]);


        /**
         * Резервируем честный знак Process «В процессе»
         *
         * @var ProductStockProduct $product
         */
        foreach($products as $product)
        {
            $total = $product->getTotal();

            for($i = 1; $i <= $total; $i++)
            {
                $ProductSignEvent = $this->productSignNew->getOneProductSign(
                    $product->getProduct(),
                    $product->getOffer(),
                    $product->getVariation(),
                    $product->getModification()
                );

                if(!$ProductSignEvent)
                {
                    $this->logger->info('Честный знак на продукцию не найден',
                        [
                            __FILE__.':'.__LINE__,
                            'ProductUid' => $product->getProduct(),
                            'ProductOfferConst' => $product->getOffer(),
                            'ProductVariationConst' => $product->getVariation(),
                            'ProductModificationConst', $product->getModification()
                        ]
                    );

                    continue;
                }

                $ProductSignProcessDTO = new ProductSignProcessDTO($ProductStockEvent->getProfile(), $ProductStockEvent->getOrder());
                $ProductSignEvent->getDto($ProductSignProcessDTO);

                $handle = $this->productSignStatusHandler->handle($ProductSignProcessDTO);

                if(!$handle instanceof ProductSign)
                {
                    $this->logger->critical(
                        sprintf('%s: Ошибка при обновлении статуса честного знака', $handle),
                        [
                            __FILE__.':'.__LINE__,
                            'ProductSignEventUid' => $ProductSignProcessDTO->getEvent()
                        ]
                    );

                    throw new InvalidArgumentException('Ошибка при обновлении статуса честного знака');
                }

                $this->logger->info('Отметили Честный знак Process «В процессе»',
                    [
                        __FILE__.':'.__LINE__,
                        'ProductSignUid' => $ProductSignEvent->getMain()
                    ]
                );
            }
        }

        $this->entityManager->flush();
    }
}