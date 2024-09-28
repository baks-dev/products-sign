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

namespace BaksDev\Products\Sign\Repository\AllProductSignExport;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Delivery\Entity\Event\DeliveryEvent;
use BaksDev\Delivery\Entity\Trans\DeliveryTrans;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Entity\Invariable\OrderInvariable;
use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Orders\Order\Entity\Products\OrderProduct;
use BaksDev\Orders\Order\Entity\Products\Price\OrderPrice;
use BaksDev\Orders\Order\Entity\User\Delivery\OrderDelivery;
use BaksDev\Orders\Order\Entity\User\OrderUser;
use BaksDev\Orders\Order\Type\Status\OrderStatus;
use BaksDev\Orders\Order\Type\Status\OrderStatus\OrderStatusCompleted;
use BaksDev\Products\Product\Entity\Event\ProductEvent;
use BaksDev\Products\Product\Entity\Offers\ProductOffer;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\ProductModification;
use BaksDev\Products\Product\Entity\Offers\Variation\ProductVariation;
use BaksDev\Products\Sign\Entity\Code\ProductSignCode;
use BaksDev\Products\Sign\Entity\Event\ProductSignEvent;
use BaksDev\Products\Sign\Entity\Invariable\ProductSignInvariable;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusDone;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use DateTimeImmutable;
use Generator;
use InvalidArgumentException;

final class AllProductSignExportRepository implements AllProductSignExportInterface
{
    private UserProfileUid|false $profile = false;

    private DateTimeImmutable|false $from = false;

    private DateTimeImmutable|false $to = false;

    public function __construct(private readonly DBALQueryBuilder $DBALQueryBuilder) {}

    public function forProfile(UserProfile|UserProfileUid|string $profile): self
    {
        if($profile instanceof UserProfile)
        {
            $profile = $profile->getId();
        }

        if(is_string($profile))
        {
            $profile = new UserProfileUid($profile);
        }

        $this->profile = $profile;

        return $this;
    }

    public function dateFrom(DateTimeImmutable|string $from): self
    {
        if(is_string($from))
        {
            $from = new DateTimeImmutable($from);
        }

        $this->from = $from;

        return $this;

    }

    public function dateTo(DateTimeImmutable|string $to): self
    {
        if(is_string($to))
        {

            $to = new DateTimeImmutable($to);
        }

        $this->to = $to;

        return $this;
    }


    public function execute(): Generator
    {
        if($this->profile === false)
        {
            throw new InvalidArgumentException('Invalid Argument profile');
        }

        if($this->from === false)
        {
            throw new InvalidArgumentException('Invalid Argument from date');
        }

        if($this->to === false)
        {
            throw new InvalidArgumentException('Invalid Argument to date');
        }

        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        $dbal
            ->from(OrderEvent::class, 'event')
            ->where('event.status = :status')
            ->setParameter(
                'status',
                OrderStatusCompleted::class,
                OrderStatus::TYPE
            );

        $dbal
            ->join(
                'event',
                Order::class,
                'main',
                'main.event = event.id'
            );


        $dbal
            ->addSelect('order_invariable.number')
            ->join(
                'main',
                OrderInvariable::class,
                'order_invariable',
                'order_invariable.main = main.id AND 
                order_invariable.event = event.id AND 
                order_invariable.profile = :profile'
            )
            ->setParameter(
                'profile',
                $this->profile,
                UserProfileUid::TYPE
            );


        $dbal
            ->leftJoin(
                'main',
                OrderUser::class,
                'order_user',
                'order_user.event = main.event'
            );

        $dbal
            ->addSelect('order_delivery.delivery_date AS delivery_date')
            ->leftJoin(
                'order_user',
                OrderDelivery::class,
                'order_delivery',
                'order_delivery.usr = order_user.id'
            );

        $dbal
            ->addSelect('delivery_event.sort AS delivery_sort')
            ->leftJoin(
                'order_delivery',
                DeliveryEvent::class,
                'delivery_event',
                'delivery_event.id = order_delivery.event'
            );

        $dbal
            ->addSelect('delivery_trans.name AS delivery_name')
            ->leftJoin(
                'order_delivery',
                DeliveryTrans::class,
                'delivery_trans',
                'delivery_trans.event = order_delivery.event AND delivery_trans.local = :local'
            );

        $dbal
            ->leftJoin(
                'event',
                OrderProduct::class,
                'order_product',
                'order_product.event = event.id'
            );


        $dbal
            ->addSelect('SUM(order_price.price) AS order_total')
            ->leftJoin(
                'event',
                OrderPrice::class,
                'order_price',
                'order_price.product = order_product.id'
            );

        $dbal
            ->leftJoin(
                'order_product',
                ProductEvent::class,
                'product_event',
                'product_event.id = order_product.product'
            );

        $dbal
            ->leftJoin(
                'order_product',
                ProductOffer::class,
                'product_offer',
                'product_offer.id = order_product.offer AND product_offer.event = order_product.product'
            );

        $dbal
            ->leftJoin(
                'order_product',
                ProductVariation::class,
                'product_variation',
                'product_variation.id = order_product.variation AND product_variation.offer = product_offer.id'
            );

        $dbal
            ->leftJoin(
                'order_product',
                ProductModification::class,
                'product_modification',
                'product_modification.id = order_product.modification AND product_modification.variation = product_variation.id'
            );


        $dbal
            ->leftJoin(
                'main',
                ProductSignEvent::class,
                'sign_event',
                '
                    sign_event.ord = main.id AND
                    sign_event.status = :sign_status
                '
            )
            ->setParameter(
                'sign_status',
                ProductSignStatusDone::class,
                ProductSignStatus::TYPE
            );

        $dbal
            ->leftJoin(
                'product_modification',
                ProductSignInvariable::class,
                'sign_invariable',
                '
                    sign_invariable.event = sign_event.id AND
                    sign_invariable.main = sign_event.main AND
                    
                    sign_invariable.product = product_event.main AND
                    sign_invariable.offer = product_offer.const AND
                    sign_invariable.variation = product_variation.const AND
                    sign_invariable.modification = product_modification.const
                '
            );


        $dbal
            ->leftJoin(
                'sign_invariable',
                ProductSignCode::class,
                'sign_code',
                '
                    sign_code.main = sign_invariable.main AND 
                    sign_code.event = sign_invariable.event
                '
            );


        $dbal->addSelect(
            "JSON_AGG
                    ( DISTINCT
        
                            JSONB_BUILD_OBJECT
                            (
                                'article', product_modification.article,
                                'price', order_price.price,
                                'code', sign_code.code
                            )
        
                    ) AS products"
        );


        $dbal->allGroupByExclude();

        return $dbal->iterateAssociative();
    }
}
