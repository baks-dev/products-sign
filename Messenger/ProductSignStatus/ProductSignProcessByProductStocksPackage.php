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
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Repository\ProductSignNew\ProductSignNewInterface;
use BaksDev\Products\Sign\Type\Id\ProductSignUid;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusProcess;
use BaksDev\Products\Sign\UseCase\Admin\Status\ProductSignProcessDTO;
use BaksDev\Products\Sign\UseCase\Admin\Status\ProductSignStatusHandler;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Messenger\ProductStockMessage;
use BaksDev\Products\Stocks\Repository\CurrentProductStocks\CurrentProductStocksInterface;
use BaksDev\Products\Stocks\Repository\ProductStocksById\ProductStocksByIdInterface;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusIncoming;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusPackage;
use BaksDev\Users\Profile\UserProfile\Repository\UserByUserProfile\UserByUserProfileInterface;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * При статусе складской заявки Package «Упаковка» - резервируем честный знак в статус Process «В процессе»
 */
#[AsMessageHandler(priority: -5)]
final readonly class ProductSignProcessByProductStocksPackage
{
    public function __construct(
        #[Target('productsSignLogger')] private LoggerInterface $logger,
        private ProductStocksByIdInterface $productStocks,
        private EntityManagerInterface $entityManager,
        private CurrentProductStocksInterface $currentProductStocks,
        private ProductSignStatusHandler $productSignStatusHandler,
        private ProductSignNewInterface $productSignNew,
        private UserByUserProfileInterface $userByUserProfile,
        private DeduplicatorInterface $deduplicator,
    ) {}


    public function __invoke(ProductStockMessage $message): void
    {

        /** Log Data */
        $dataLogs['ProductStockUid'] = (string) $message->getId();
        $dataLogs['ProductStockEventUid'] = (string) $message->getEvent();
        $dataLogs['LastProductStockEventUid'] = (string) $message->getLast();

        $ProductStockEvent = $this->currentProductStocks->getCurrentEvent($message->getId());

        if(!$ProductStockEvent)
        {
            $dataLogs[0] = self::class.':'.__LINE__;
            $this->logger->critical('products-sign: Не найдено событие ProductStock', $dataLogs);

            return;
        }

        if(false === $ProductStockEvent->equalsProductStockStatus(ProductStockStatusPackage::class))
        {
            $dataLogs[0] = self::class.':'.__LINE__;
            $this->logger->notice('Не резервируем честный знак: Складская заявка не является Package «Упаковка»', $dataLogs);

            return;
        }

        if(!$ProductStockEvent->getOrder())
        {
            $dataLogs[0] = self::class.':'.__LINE__;
            $this->logger->notice('Не резервируем честный знак: упаковка без идентификатора заказа', $dataLogs);

            return;
        }

        /** Определяем пользователя профилю в заявке */
        $User = $this
            ->userByUserProfile
            ->forProfile($ProductStockEvent->getStocksProfile())
            ->find();

        if(false === $User)
        {
            $dataLogs[0] = self::class.':'.__LINE__;
            $this->logger
                ->critical(
                    sprintf('products-sign: Невозможно зарезервировать «Честный знак»! Пользователь профиля %s не найден ', $ProductStockEvent->getStocksProfile()),
                    $dataLogs
                );

            return;
        }

        if($message->getLast())
        {
            $lastProductStockEvent = $this
                ->entityManager
                ->getRepository(ProductStockEvent::class)
                ->find($message->getLast());

            /** Если предыдущая заявка на перемещение и совершается поступление по этой заявке - резерв уже был */
            if($lastProductStockEvent === null || $lastProductStockEvent->getStatus()->equals(new ProductStockStatusIncoming()) === true)
            {
                $dataLogs[0] = self::class.':'.__LINE__;
                $this->logger->notice('Не резервируем честный знак: Складская заявка при поступлении на склад по заказу (резерв уже имеется)', $dataLogs);

                return;
            }
        }

        // Получаем всю продукцию в ордере со статусом Package (УПАКОВКА)
        $products = $this->productStocks->getProductsPackageStocks($message->getId());

        if(empty($products))
        {
            $dataLogs[0] = self::class.':'.__LINE__;
            $this->logger->warning('Заявка на упаковку не имеет продукции в коллекции', $dataLogs);

            return;
        }

        $Deduplicator = $this->deduplicator
            ->namespace('products-sign')
            ->deduplication([
                (string) $message->getId(),
                ProductSignStatusProcess::STATUS,
                md5(self::class)
            ]);

        if($Deduplicator->isExecuted())
        {
            return;
        }

        $this->logger->info('Добавляем резерв кода Честный знак статус Process «В процессе»:');

        /**
         * Резервируем честный знак Process «В процессе»
         *
         * @var ProductStockProduct $product
         */


        $ProductSignUid = new ProductSignUid();

        foreach($products as $product)
        {
            $total = $product->getTotal();

            for($i = 1; $i <= $total; $i++)
            {
                if(($i % 500) === 0)
                {
                    $ProductSignUid = new ProductSignUid();
                }

                $ProductSignEvent = $this->productSignNew
                    ->forUser($User)
                    ->forProfile($ProductStockEvent->getStocksProfile())
                    ->forProduct($product->getProduct())
                    ->forOfferConst($product->getOffer())
                    ->forVariationConst($product->getVariation())
                    ->forModificationConst($product->getModification())
                    ->getOneProductSign();

                if(!$ProductSignEvent)
                {
                    $dataLogs[0] = self::class.':'.__LINE__;
                    $dataLogs['usr'] = (string) $User;
                    $dataLogs['profile'] = (string) $ProductStockEvent->getStocksProfile();
                    $dataLogs['product'] = (string) $product->getProduct();
                    $dataLogs['offer'] = (string) $product->getOffer();
                    $dataLogs['variation'] = (string) $product->getVariation();
                    $dataLogs['modification'] = (string) $product->getModification();

                    $this->logger->warning('Честный знак на продукцию не найден', $dataLogs);

                    continue;
                }

                $ProductSignProcessDTO = new ProductSignProcessDTO($ProductStockEvent->getStocksProfile(), $ProductStockEvent->getOrder());
                $ProductSignInvariableDTO = $ProductSignProcessDTO->getInvariable();
                $ProductSignInvariableDTO->setPart($ProductSignUid);

                $ProductSignEvent->getDto($ProductSignProcessDTO);

                $handle = $this->productSignStatusHandler->handle($ProductSignProcessDTO);

                if(!$handle instanceof ProductSign)
                {
                    $dataLogs[0] = self::class.':'.__LINE__;
                    $dataLogs['ProductSignEventUid'] = (string) $ProductSignProcessDTO->getEvent();

                    $this->logger->critical(
                        sprintf('%s: Ошибка при обновлении статуса честного знака', $handle),
                        $dataLogs
                    );

                    throw new InvalidArgumentException('Ошибка при обновлении статуса честного знака');
                }

                $dataLogs[0] = self::class.':'.__LINE__;
                $dataLogs['ProductSignUid'] = (string) $ProductSignEvent->getMain();

                $this->logger->info('Отметили Честный знак Process «В процессе»', $dataLogs);

            }
        }

        $Deduplicator->save();
    }
}
