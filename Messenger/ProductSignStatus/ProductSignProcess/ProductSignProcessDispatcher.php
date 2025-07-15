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

namespace BaksDev\Products\Sign\Messenger\ProductSignStatus\ProductSignProcess;


use BaksDev\Delivery\Type\Id\Choice\TypeDeliveryPickup;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Repository\CurrentOrderEvent\CurrentOrderEventInterface;
use BaksDev\Ozon\Orders\BaksDevOzonOrdersBundle;
use BaksDev\Ozon\Orders\Type\DeliveryType\TypeDeliveryDbsOzon;
use BaksDev\Ozon\Orders\Type\DeliveryType\TypeDeliveryFbsOzon;
use BaksDev\Products\Sign\Entity\Event\ProductSignEvent;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Repository\ProductSignNew\ProductSignNewInterface;
use BaksDev\Products\Sign\UseCase\Admin\Status\ProductSignProcessDTO;
use BaksDev\Products\Sign\UseCase\Admin\Status\ProductSignStatusHandler;
use BaksDev\Users\Profile\TypeProfile\Type\Id\Choice\TypeProfileIndividual;
use BaksDev\Users\Profile\TypeProfile\Type\Id\Choice\TypeProfileOrganization;
use BaksDev\Users\Profile\TypeProfile\Type\Id\Choice\TypeProfileWorker;
use BaksDev\Users\Profile\UserProfile\Repository\CurrentUserProfileEvent\CurrentUserProfileEventInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Wildberries\Orders\BaksDevWildberriesOrdersBundle;
use BaksDev\Wildberries\Orders\Type\DeliveryType\TypeDeliveryDbsWildberries;
use BaksDev\Wildberries\Orders\Type\DeliveryType\TypeDeliveryFboWildberries;
use BaksDev\Wildberries\Orders\Type\DeliveryType\TypeDeliveryFbsWildberries;
use BaksDev\Yandex\Market\Orders\BaksDevYandexMarketOrdersBundle;
use BaksDev\Yandex\Market\Orders\Type\DeliveryType\TypeDeliveryDbsYaMarket;
use BaksDev\Yandex\Market\Orders\Type\DeliveryType\TypeDeliveryFbsYaMarket;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 0)]
final readonly class ProductSignProcessDispatcher
{
    public function __construct(
        #[Target('productsSignLogger')] private LoggerInterface $logger,
        private ProductSignStatusHandler $ProductSignStatusHandler,
        private ProductSignNewInterface $ProductSignNew,
        private CurrentOrderEventInterface $CurrentOrderEvent,
        private CurrentUserProfileEventInterface $CurrentUserProfileEvent
    ) {}

    public function __invoke(ProductSignProcessMessage $message): void
    {

        /**
         * Получаем информацию о заказе
         */
        $CurrentOrderEvent = $this->CurrentOrderEvent
            ->forOrder($message->getOrder())
            ->find();


        /**
         * Если тип заказа Wildberries, Озон, Яндекс, Авито
         * Присваиваем владельца честного знака в качестве продавца
         */

        if($this->isMarketplace($CurrentOrderEvent))
        {
            /**
             * При реализации через маркетплейсы SELLER всегда должен быть NULL
             * если указан SELLER - реализация только через корзину и собственную доставку
             *
             * @see ProductSignInvariable
             *
             * Поиск любого доступного честного знака,
             * должен определится честный знак у которого свойство SELLER === NULL
             * для этого передаем тестовый идентификатор профиля
             *
             */

            $ProductSignEvent = $this->ProductSignNew
                ->forUser($message->getUser())
                ->forProfile(new UserProfileUid(UserProfileUid::TEST)) // передаем тестовый идентификатор для поиска по NULL
                ->forProduct($message->getProduct())
                ->forOfferConst($message->getOffer())
                ->forVariationConst($message->getVariation())
                ->forModificationConst($message->getModification())
                ->getOneProductSign();
        }
        else
        {
            $ProductSignEvent = $this->ProductSignNew
                ->forUser($message->getUser())
                ->forProfile($message->getProfile())
                ->forProduct($message->getProduct())
                ->forOfferConst($message->getOffer())
                ->forVariationConst($message->getVariation())
                ->forModificationConst($message->getModification())
                ->getOneProductSign();
        }

        if(false === ($ProductSignEvent instanceof ProductSignEvent))
        {
            $this->logger->warning(
                'Честный знак на продукцию не найден',
                [var_export($message, true), self::class.':'.__LINE__],
            );

            return;
        }


        $ProductSignProcessDTO = new ProductSignProcessDTO(
            $message->getProfile(),
            $message->getOrder(),
        );

        $ProductSignInvariableDTO = $ProductSignProcessDTO->getInvariable();
        $ProductSignInvariableDTO->setPart($message->getPart());


        /** Если тип заказа Wildberries, Озон, Яндекс, Озон - Присваиваем владельца в качестве продавца */

        if($this->isMarketplace($CurrentOrderEvent))
        {
            $ProductSignInvariableDTO
                ->setSeller($ProductSignEvent->getOwnerSignProfile());
        }

        /**
         * Если тип профиля клиента Сотрудник - присваиваем Владельца
         */

        $UserProfileEventUid = $CurrentOrderEvent->getClientProfile();

        $UserProfileEvent = $this->CurrentUserProfileEvent->findByEvent($UserProfileEventUid);

        $TypeProfileUid = $UserProfileEvent->getType();

        /**
         * Если тип клиента Сотрудник - присваиваем NULL (Не передаем и не списываем честный знак)
         */
        if(true === $TypeProfileUid->equals(TypeProfileWorker::class))
        {
            $ProductSignInvariableDTO->setNullSeller();
        }

        /**
         * Если тип профиля клиента «Организация» либо «Индивидуальный предприниматель»
         * присваиваем в качестве продавца профиль клиента
         */
        if(
            false === $TypeProfileUid->equals(TypeProfileOrganization::class) ||
            false === $TypeProfileUid->equals(TypeProfileIndividual::class)
        )
        {
            $ProductSignInvariableDTO
                ->setSeller($UserProfileEvent->getMain());
        }


        /**
         * Присваиваем идентификатор склада в качестве продавца
         */
        $ProductSignInvariableDTO
            ->setSeller($CurrentOrderEvent->getOrderProfile());

        $ProductSignEvent->getDto($ProductSignProcessDTO);

        $handle = $this->ProductSignStatusHandler->handle($ProductSignProcessDTO);

        if(!$handle instanceof ProductSign)
        {
            $this->logger->critical(
                sprintf('%s: Ошибка при обновлении статуса честного знака', $handle),
                [var_export($message, true), self::class.':'.__LINE__],
            );

            throw new InvalidArgumentException('Ошибка при обновлении статуса честного знака');
        }

        $this->logger->info(
            'Отметили Честный знак Process «В резерве»',
            [var_export($message, true), self::class.':'.__LINE__],
        );
    }

    public function isMarketplace(OrderEvent $CurrentOrderEvent): bool
    {
        /** Если тип заказа Wildberries, Озон, Яндекс, Озон - Присваиваем владельца в качестве продавца */

        if(class_exists(BaksDevYandexMarketOrdersBundle::class))
        {
            if(
                $CurrentOrderEvent->isDeliveryTypeEquals(TypeDeliveryFbsYaMarket::class) ||
                $CurrentOrderEvent->isDeliveryTypeEquals(TypeDeliveryDbsYaMarket::class)
            )
            {

                return true;
            }
        }


        if(class_exists(BaksDevOzonOrdersBundle::class))
        {
            if(
                $CurrentOrderEvent->isDeliveryTypeEquals(TypeDeliveryDbsOzon::class) ||
                $CurrentOrderEvent->isDeliveryTypeEquals(TypeDeliveryFbsOzon::class)
            )
            {

                return true;
            }
        }

        if(class_exists(BaksDevWildberriesOrdersBundle::class))
        {
            if(
                $CurrentOrderEvent->isDeliveryTypeEquals(TypeDeliveryDbsWildberries::class) ||
                $CurrentOrderEvent->isDeliveryTypeEquals(TypeDeliveryFbsWildberries::class) ||
                $CurrentOrderEvent->isDeliveryTypeEquals(TypeDeliveryFboWildberries::class)
            )
            {

                return true;
            }
        }

        return false;
    }
}
