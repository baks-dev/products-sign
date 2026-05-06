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
 *
 */

declare(strict_types=1);

namespace BaksDev\Products\Sign\Messenger\ProductSignStatus;

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Orders\Order\Repository\Items\AllOrderProductItemConst\AllOrderProductItemConstInterface;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierByEventInterface;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierResult;
use BaksDev\Products\Sign\Messenger\ProductSignStatus\ProductSignCancel\ProductSignCancelMessage;
use BaksDev\Products\Sign\Messenger\ProductSignStatus\ProductSignProcess\ProductSignProcessMessage;
use BaksDev\Products\Sign\Repository\ProductSignByOrder\ProductSignByOrderInterface;
use BaksDev\Products\Sign\Type\Id\ProductSignUid;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Messenger\Orders\EditProductStockTotal\EditProductStockTotalMessage;
use BaksDev\Products\Stocks\Repository\ProductStocksEvent\ProductStocksEventInterface;
use BaksDev\Products\Stocks\Type\Event\ProductStockEventUid;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusCompleted;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusIncoming;
use BaksDev\Users\Profile\UserProfile\Repository\UserByUserProfile\UserByUserProfileInterface;
use BaksDev\Users\User\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Запускаем процесс резервирования и снятия резерва с Честных знаков при изменении складской заявки
 *
 * @note складская заявка изменяется когда редактируется заказ
 */
#[Autoconfigure(shared: false)]
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
        private CurrentProductIdentifierByEventInterface $CurrentProductIdentifierRepository,
        private ProductSignByOrderInterface $productSignByOrderRepository,
    ) {}

    public function __invoke(EditProductStockTotalMessage $message): void
    {
        $Deduplicator = $this->deduplicator
            ->namespace('products-sign')
            ->deduplication([
                (string) $message->getEvent(),
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

        /**
         * Если заявка уже выполнена - это происходит возврат -
         * завершаем обработчик
         *
         * @see ProductSignReturnByOrderReturnDispatcher
         */
        if(true === $ProductStockEvent->equalsProductStockStatus(ProductStockStatusCompleted::class))
        {
            return;
        }

        $OrderUid = $ProductStockEvent->getOrder();

        if(false === ($OrderUid instanceof OrderUid))
        {
            $this->logger->notice(
                'Не резервируем честный знак: в складской заявке отсутствует идентификатора заказа',
                [var_export($message, true), self::class.':'.__LINE__],
            );

            return;
        }

        /** Проверяем, есть ли предыдущее событие  */
        if(true === ($message->getLast() instanceof ProductStockEventUid))
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


        /** Получаем всю продукцию в из складской заявки */
        $products = $ProductStockEvent->getProduct();

        if(true === $products->isEmpty())
        {
            $this->logger->warning(
                message: sprintf('%s: Отсутствует продукция в складской заявке', $ProductStockEvent->getNumber()),
                context: [var_export($message, true), self::class.':'.__LINE__],
            );

            return;
        }

        if(false === $ProductStockEvent->isInvariable())
        {
            $this->logger->warning(
                message: sprintf('%s: не определено ProductStocksInvariable', $ProductStockEvent->getNumber()),
                context: [self::class.':'.__LINE__, var_export($message, true)],
            );

            return;
        }

        $UserProfileUid = $ProductStockEvent->getInvariable()->getProfile();

        $User = $this
            ->userByUserProfileRepository
            ->forProfile($UserProfileUid)
            ->find();

        if(false === ($User instanceof User))
        {
            $this->logger->critical(
                message: sprintf(
                    'products-sign: Невозможно зарезервировать «Честный знак»! Пользователь профиля %s не найден ',
                    $UserProfileUid,
                ),
                context: [var_export($message, true), self::class.':'.__LINE__],
            );

            return;
        }

        /**
         * Снимаем резервы с Честных знаков на удаленные единицы продукты из заказа
         */

        $result = $this->productSignByOrderRepository
            ->forOrder($OrderUid)
            ->withoutItem() // Честные знаки, у которых нет связи с единицей продукта
            ->findAll();

        if(false !== $result)
        {
            $this->logger->info(
                message: sprintf('%s: снимаем резерв Честных знаков', $OrderUid),
                context: [self::class.':'.__LINE__],
            );

            foreach($result as $ProductSignByOrderResult)
            {
                $this->MessageDispatch->dispatch(
                    message: new ProductSignCancelMessage(
                        $UserProfileUid,
                        $ProductSignByOrderResult->getSignEvent(),
                    ),
                    transport: 'products-sign',
                );
            }
        }

        /**
         * Резервируем честный знак Process «В резерве»
         */

        /** Все единицы продукта из заказа */
        $productItemsConst = $this->allOrderProductItemConstRepository
            ->withoutSign()
            ->findAll($OrderUid);

        /** Если нет для резерва продуктов - завершаем обработчик */
        if(false === $productItemsConst)
        {
            $this->logger->info(
                message: sprintf('%s: Не найдены новые единицы продукции для резервирования Честных знаков', $ProductStockEvent->getNumber()),
                context: [
                    self::class.':'.__LINE__,
                    var_export($OrderUid, true),
                ],
            );

            return;
        }

        $this->logger->info(
            message: sprintf('%s: резервируем Честные знаки по КОЛИЧЕСТВУ продукции', $ProductStockEvent->getNumber()),
            context: [self::class.':'.__LINE__],
        );


        $ProductSignPart = new ProductSignUid(); // Генерируем идентификатор партии

        foreach($productItemsConst as $key => $const)
        {
            $DeduplicatorConst = $this->deduplicator
                ->namespace('products-sign')
                ->deduplication([
                    (string) $const,
                    self::class,
                ]);

            if($DeduplicatorConst->isExecuted())
            {
                continue;
            }

            $orderProductIds = $const->getParams();

            if(null === $orderProductIds)
            {
                $this->logger->critical(
                    message: 'products-sign: Невозможно получить идентификаторы OrderProduct',
                    context: [self::class.':'.__LINE__, (string) $const],
                );

                continue;
            }

            /** Получаем константы продукта */
            $CurrentProductIdentifierResult = $this->CurrentProductIdentifierRepository
                ->forEvent($orderProductIds['product'])
                ->forOffer($orderProductIds['offer'])
                ->forVariation($orderProductIds['variation'])
                ->forModification($orderProductIds['modification'])
                ->find();

            if(false === $CurrentProductIdentifierResult instanceof CurrentProductIdentifierResult)
            {
                $this->logger->critical(
                    message: 'products-sign: Невозможно получить CurrentProductIdentifierResult',
                    context: [
                        self::class.':'.__LINE__,
                        var_export($const, true),
                    ],
                );

                return;
            }

            $ProductSignProcessMessage = new ProductSignProcessMessage(
                order: $OrderUid,
                part: $ProductSignPart,

                user: $User->getId(),
                profile: $UserProfileUid,

                product: $CurrentProductIdentifierResult->getProduct(),
                offer: $CurrentProductIdentifierResult->getOfferConst(),
                variation: $CurrentProductIdentifierResult->getVariationConst(),
                modification: $CurrentProductIdentifierResult->getModificationConst(),

                itemConst: $const,
            );

            $this->MessageDispatch
                ->dispatch(
                    message: $ProductSignProcessMessage,
                    transport: 'products-sign',
                );


            /** Генерируем новый идентификатор партии по 100 шт */
            if((($key + 1) % 100) === 0)
            {
                /** Переопределяем группу */
                $ProductSignPart = new ProductSignUid();
            }

            $DeduplicatorConst->save();
        }

        $Deduplicator->save();
    }
}
