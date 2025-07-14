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

namespace BaksDev\Products\Sign\Repository\ProductSignByOrder;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Orders\Order\Entity\User\OrderUser;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Product\Entity\Product;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Sign\Entity\Code\ProductSignCode;
use BaksDev\Products\Sign\Entity\Event\ProductSignEvent;
use BaksDev\Products\Sign\Entity\Invariable\ProductSignInvariable;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusDone;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusProcess;
use BaksDev\Users\Profile\UserProfile\Entity\Event\UserProfileEvent;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Generator;
use InvalidArgumentException;

final class ProductSignByOrderRepository implements ProductSignByOrderInterface
{
    /** Фильтр по продукту */

    private ProductUid|false $product = false;

    private ProductOfferConst|false $offer = false;

    private ProductVariationConst|false $variation = false;

    private ProductModificationConst|false $modification = false;

    /** Фильтр по заказу */

    private OrderUid|false $order = false;

    private UserProfileUid|false $profile = false;

    private ProductSignStatus $status;

    private string|false $part = false;


    public function __construct(private readonly DBALQueryBuilder $DBALQueryBuilder)
    {
        /** По умолчанию возвращаем знаки со статусом Process «В резерве» */
        $this->status = new ProductSignStatus(ProductSignStatusProcess::class);
    }


    /** Фильтр по продукту */
    public function product(Product|ProductUid|string $product): self
    {
        if(is_string($product))
        {
            $product = new ProductUid($product);
        }

        if($product instanceof Product)
        {
            $product = $product->getId();
        }

        $this->product = $product;

        return $this;
    }

    public function offer(ProductOfferConst|string|null|false $offer): self
    {
        if(empty($offer))
        {
            $this->offer = false;
            return $this;
        }

        if(is_string($offer))
        {
            $offer = new ProductOfferConst($offer);
        }

        $this->offer = $offer;

        return $this;
    }

    public function variation(ProductVariationConst|string|null|false $variation): self
    {
        if(empty($variation))
        {
            $this->variation = false;
            return $this;
        }

        if(is_string($variation))
        {
            $variation = new ProductVariationConst($variation);
        }

        $this->variation = $variation;

        return $this;
    }

    public function modification(ProductModificationConst|string|null|false $modification): self
    {
        if(empty($modification))
        {
            $this->modification = false;
            return $this;
        }

        if(is_string($modification))
        {
            $modification = new ProductModificationConst($modification);
        }

        $this->modification = $modification;

        return $this;
    }


    public function profile(UserProfileUid|string|UserProfile $profile): self
    {
        if(is_string($profile))
        {
            $profile = new UserProfileUid($profile);
        }

        if($profile instanceof UserProfile)
        {
            $profile = $profile->getId();
        }

        $this->profile = $profile;

        return $this;

    }

    public function forPart(string $part): self
    {
        $this->part = $part;

        return $this;
    }

    public function forOrder(Order|OrderUid|string $order): self
    {
        if($order instanceof Order)
        {
            $order = $order->getId();
        }

        if(is_string($order))
        {
            $order = new OrderUid($order);
        }

        $this->order = $order;

        return $this;
    }

    /**
     * Возвращает знаки со статусом Done «Выполнен»
     */
    public function withStatusDone(): self
    {
        $this->status = new ProductSignStatus(ProductSignStatusDone::class);
        return $this;
    }


    /**
     * Метод возвращает все штрихкоды «Честный знак» для печати по идентификатору заказа
     * По умолчанию возвращает знаки со статусом Process «В резерве»
     *
     * @return Generator<int, ProductSignByOrderResult>|false
     */
    public function findAll(): Generator|false
    {
        if($this->order === false)
        {
            throw new InvalidArgumentException('Не передан обязательный параметр order через вызов метода ->forOrder(...)');
        }

        /*if($this->part === false)
        {
            throw new InvalidArgumentException('Invalid Argument Part');
        }*/

        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $dbal->from(
            ProductSignEvent::class,
            'event'
        );

        $dbal
            ->where('event.ord = :ord')
            ->setParameter('ord', $this->order, OrderUid::TYPE);

        $dbal
            ->andWhere('event.status = :status')
            ->setParameter('status', $this->status, ProductSignStatus::TYPE);

        if($this->profile !== false)
        {
            $dbal->leftJoin(
                'event',
                Order::class,
                'ord',
                'ord.id = event.ord'
            );


            $dbal->leftJoin(
                'ord',
                OrderUser::class,
                'ord_usr',
                'ord_usr.event = ord.event'
            );

            $dbal
                ->join(
                    'ord_usr',
                    UserProfileEvent::class,
                    'profile_event',
                    'profile_event.id = ord_usr.profile AND profile_event.profile = :profile'
                )
                ->setParameter('profile', $this->profile, UserProfileUid::TYPE);
        }

        $dbal
            ->addSelect('main.id AS sign_id')
            ->addSelect('main.event AS sign_event')
            ->join(
            'event',
            ProductSign::class,
            'main',
            'main.id = event.main'
        );


        if($this->product)
        {
            $offerParam = $this->offer ? ' = :offer' : ' IS NULL';
            !$this->offer ?: $dbal->setParameter('offer', $this->offer, ProductOfferConst::TYPE);

            $variationParam = $this->variation ? ' = :variation' : ' IS NULL';
            !$this->variation ?: $dbal->setParameter('variation', $this->variation, ProductVariationConst::TYPE);

            $modificationParam = $this->modification ? ' = :modification' : ' IS NULL';
            !$this->modification ?: $dbal->setParameter('modification', $this->modification, ProductModificationConst::TYPE);

            $dbal
                ->join(
                    'event',
                    ProductSignInvariable::class,
                    'invariable',
                    '
                    invariable.main = main.id AND 
                    invariable.product = :product AND
                    invariable.offer '.$offerParam.' AND
                    invariable.variation '.$variationParam.' AND
                    invariable.modification '.$modificationParam.'
                '.($this->part ? ' AND invariable.part = :part' : '')
                )
                ->setParameter(
                    key: 'product',
                    value: $this->product,
                    type: ProductUid::TYPE
                );

            if($this->part)
            {
                $dbal->setParameter(
                    key: 'part',
                    value: $this->part,
                );
            }

        }

        $dbal
            ->addSelect(
                "
                CASE
                   WHEN code.name IS NOT NULL 
                   THEN CONCAT ( '/upload/".$dbal->table(ProductSignCode::class)."' , '/', code.name)
                   ELSE NULL
                END AS code_image
            "
            )
            ->addSelect("code.ext AS code_ext")
            ->addSelect("code.cdn AS code_cdn")
            ->addSelect("code.code AS code_string")
            ->leftJoin(
                'event',
                ProductSignCode::class,
                'code',
                'code.main = main.id'
            );

        $result = $dbal->fetchAllHydrate(ProductSignByOrderResult::class);

        return $result->valid() ? $result : false;
    }


}
