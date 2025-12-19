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
 *
 */

declare(strict_types=1);

namespace BaksDev\Products\Sign\Messenger\ProductSignStatus;

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Orders\Order\Repository\Items\AllOrderProductItemConst\AllOrderProductItemConstInterface;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Sign\Messenger\ProductSignStatus\ProductSignProcess\ProductSignProcessMessage;
use BaksDev\Products\Sign\Type\Id\ProductSignUid;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusProcess;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Messenger\ProductStockMessage;
use BaksDev\Products\Stocks\Repository\ProductStocksEvent\ProductStocksEventInterface;
use BaksDev\Products\Stocks\Type\Event\ProductStockEventUid;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusIncoming;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusPackage;
use BaksDev\Users\Profile\UserProfile\Repository\UserByUserProfile\UserByUserProfileInterface;
use BaksDev\Users\User\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * При статусе складской заявки Package «Упаковка» - резервируем честный знак в статус Process «В резерве»
 */
#[AsMessageHandler(priority: -5)]
final readonly class ProductSignProcessByProductStocksPackageDispatcher
{
    public function __construct(
        #[Target('productsSignLogger')] private LoggerInterface $logger,
        private DeduplicatorInterface $deduplicator,
        private MessageDispatchInterface $MessageDispatch,
        private ProductStocksEventInterface $ProductStocksEventRepository,
        private UserByUserProfileInterface $userByUserProfileRepository,
        private AllOrderProductItemConstInterface $allOrderProductItemConstRepository,
    ) {}

    public function __invoke(ProductStockMessage $message): void
    {
        $Deduplicator = $this->deduplicator
            ->namespace('products-sign')
            ->deduplication([
                (string) $message->getId(),
                ProductSignStatusProcess::STATUS,
                self::class,
            ]);

        if($Deduplicator->isExecuted())
        {
            return;
        }

        $ProductStockEvent = $this->ProductStocksEventRepository
            ->forEvent($message->getEvent())
            ->find();

        if(false === ($ProductStockEvent instanceof ProductStockEvent))
        {
            $this->logger->critical(
                'products-sign: Не найдено событие ProductStock',
                [var_export($message, true), self::class.':'.__LINE__],
            );

            return;
        }

        if(false === $ProductStockEvent->equalsProductStockStatus(ProductStockStatusPackage::class))
        {
            $this->logger->notice(
                'Не резервируем честный знак: Складская заявка не является Package «Упаковка»',
                [var_export($message, true), self::class.':'.__LINE__],
            );

            return;
        }

        if(false === ($ProductStockEvent->getOrder() instanceof OrderUid))
        {
            $this->logger->notice(
                'Не резервируем честный знак: упаковка без идентификатора заказа',
                [var_export($message, true), self::class.':'.__LINE__],
            );

            return;
        }

        if($message->getLast() instanceof ProductStockEventUid)
        {
            $lastProductStockEvent = $this->ProductStocksEventRepository
                ->forEvent($message->getLast())
                ->find();

            /** Если предыдущая заявка на перемещение и совершается поступление по этой заявке - резерв уже был */
            if(false === ($lastProductStockEvent instanceof ProductStockEvent) || $lastProductStockEvent->equalsProductStockStatus(ProductStockStatusIncoming::class) === true)
            {
                $this->logger->notice(
                    'Не резервируем честный знак: Складская заявка при поступлении на склад по заказу (резерв уже имеется)',
                    [var_export($message, true), self::class.':'.__LINE__],
                );

                return;
            }
        }


        // Получаем всю продукцию в ордере
        $products = $ProductStockEvent->getProduct();

        if($products->isEmpty())
        {
            $this->logger->warning(
                'Заявка на упаковку не имеет продукции в коллекции',
                [var_export($message, true), self::class.':'.__LINE__],
            );

            return;
        }

        if(false === $ProductStockEvent->isInvariable())
        {
            $this->logger->warning(
                'Заявка на упаковку не может определить ProductStocksInvariable',
                [self::class.':'.__LINE__, var_export($message, true)],
            );

            return;
        }

        $UserProfileUid = $ProductStockEvent->getInvariable()?->getProfile();

        $User = $this
            ->userByUserProfileRepository
            ->forProfile($UserProfileUid)
            ->find();


        if(false === ($User instanceof User))
        {
            $this->logger
                ->critical(
                    sprintf(
                        'products-sign: Невозможно зарезервировать «Честный знак»! Пользователь профиля %s не найден ',
                        $UserProfileUid,
                    ),
                    [var_export($message, true), self::class.':'.__LINE__],
                );

            return;
        }

        /**
         * Резервируем честный знак Process «В резерве»
         *
         * @var ProductStockProduct $product
         */
        foreach($products as $product)
        {
            $ProductSignPart = new ProductSignUid();

            $OrderUid = $ProductStockEvent->getOrder();

            /** Все единицы продукта из заказа */
            $orderProductItemsConst = $this->allOrderProductItemConstRepository
                ->findAll($OrderUid);

            /** Если продукты в заказе НЕ РАЗДЕЛЕНЫ - резервируем Честные знаки по КОЛИЧЕСТВУ продукции */
            if(false === $orderProductItemsConst)
            {
                $this->logger->info(
                    message: 'резервируем Честные знаки по КОЛИЧЕСТВУ продукции',
                    context: [self::class.':'.__LINE__],
                );

                $ProductSignProcessMessage = new ProductSignProcessMessage(
                    order: $OrderUid,
                    part: $ProductSignPart,
                    user: $User->getId(),
                    profile: $UserProfileUid,
                    product: $product->getProduct(),
                    offer: $product->getOffer(),
                    variation: $product->getVariation(),
                    modification: $product->getModification(),
                );

                $productTotal = $product->getTotal();

                for($i = 1; $i <= $productTotal; $i++)
                {
                    $ProductSignProcessMessage->setPart($ProductSignPart);

                    $this->MessageDispatch
                        ->dispatch(
                            message: $ProductSignProcessMessage,
                            transport: 'products-sign',
                        );

                    /** Разбиваем партии по 100 шт */
                    if(($i % 100) === 0)
                    {
                        $ProductSignPart = new ProductSignUid();
                    }
                }
            }

            /** Если продукты в заказе РАЗДЕЛЕНЫ - резервируем Честные знаки по ЕДИНИЦАМ продукции */
            if(false !== $orderProductItemsConst)
            {
                $this->logger->info(
                    message: 'резервируем Честные знаки по ЕДИНИЦАМ продукции',
                    context: [self::class.':'.__LINE__],
                );

                foreach($orderProductItemsConst as $key => $OrderProductItemConst)
                {
                    $ProductSignProcessMessage = new ProductSignProcessMessage(
                        order: $OrderUid,
                        part: $ProductSignPart,
                        user: $User->getId(),
                        profile: $UserProfileUid,
                        product: $product->getProduct(),
                        offer: $product->getOffer(),
                        variation: $product->getVariation(),
                        modification: $product->getModification(),

                        orderItemConst: $OrderProductItemConst
                    );

                    /** Разбиваем партии по 100 шт */
                    if((($key + 1) % 100) === 0)
                    {
                        $ProductSignProcessMessage->setPart(new ProductSignUid());
                    }

                    $this->MessageDispatch
                        ->dispatch(
                            message: $ProductSignProcessMessage,
                            transport: 'products-sign',
                        );
                }
            }
        }

        $Deduplicator->save();
    }
}
