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
 */

declare(strict_types=1);

namespace BaksDev\Products\Sign\Repository\ProductSignNew;

use BaksDev\Core\Doctrine\ORMQueryBuilder;
use BaksDev\Products\Product\Entity\Product;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Sign\Entity\Event\ProductSignEvent;
use BaksDev\Products\Sign\Entity\Invariable\ProductSignInvariable;
use BaksDev\Products\Sign\Entity\Modify\ProductSignModify;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusNew;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusReturn;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Entity\User;
use BaksDev\Users\User\Type\Id\UserUid;
use Doctrine\DBAL\ArrayParameterType;
use InvalidArgumentException;

final class ProductSignNewRepository implements ProductSignNewInterface
{
    private ?UserUid $user = null;

    private ?UserProfileUid $profile = null;

    private ?ProductUid $product = null;

    /** Продукция */

    private ProductOfferConst|false $offer = false;

    private ProductVariationConst|false $variation = false;

    private ProductModificationConst|false $modification = false;

    private string|false $part = false;

    public function __construct(private readonly ORMQueryBuilder $ORMQueryBuilder) {}

    public function forUser(User|UserUid|string $user): self
    {
        if(empty($user))
        {
            $this->user = null;
            return $this;
        }

        if($user instanceof User)
        {
            $user = $user->getId();
        }

        if(is_string($user))
        {
            $user = new UserUid($user);
        }

        $this->user = $user;

        return $this;
    }

    public function forProfile(UserProfile|UserProfileUid|string $profile): self
    {
        if(empty($profile))
        {
            $this->profile = null;
            return $this;
        }

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

    public function forProduct(Product|ProductUid|string $product): self
    {
        if(empty($product))
        {
            $this->product = null;
            return $this;
        }

        if($product instanceof Product)
        {
            $product = $product->getId();
        }

        if(is_string($product))
        {
            $product = new ProductUid($product);
        }

        $this->product = $product;

        return $this;
    }

    public function forOfferConst(ProductOfferConst|string|null|false $offer): self
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

    public function forVariationConst(ProductVariationConst|string|null|false $variation): self
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

    public function forModificationConst(ProductModificationConst|string|null|false $modification): self
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

    public function forPart(string|null|false $part): self
    {
        $this->part = empty($part) ? false : $part;
        return $this;
    }


    /**
     * Метод возвращает один Честный знак на указанную продукцию со статусом New «Новый»
     */
    public function getOneProductSign(): ProductSignEvent|false
    {
        if(!isset($this->user, $this->profile, $this->product))
        {
            throw new InvalidArgumentException('Не определено обязательное свойство user, profile, либо product');
        }

        $orm = $this->ORMQueryBuilder->createQueryBuilder(self::class);

        $orm
            ->from(ProductSignInvariable::class, 'invariable');

        $orm
            ->where('invariable.usr = :usr')
            ->setParameter(
                key: 'usr',
                value: $this->user,
                type: UserUid::TYPE,
            );

        if($this->part !== false)
        {
            $orm
                ->andWhere('invariable.part = :part')
                ->setParameter(
                    key: 'part',
                    value: $this->part,
                );
        }


        $orm
            ->andWhere('invariable.product = :product')
            ->setParameter(
                key: 'product',
                value: $this->product,
                type: ProductUid::TYPE,
            );


        /**
         * Если передан тестовый идентификатор - поиск только по NULL
         * при реализации через маркетплейсы SELLER всегда должен быть NULL
         * если указан SELLER - реализация только через корзину и собственную доставку
         */
        if($this->profile->equals(UserProfileUid::TEST))
        {
            $orm
                ->andWhere('invariable.seller IS NULL');
        }
        else
        {
            $orm
                ->andWhere('(invariable.seller IS NULL OR invariable.seller = :seller)')
                ->setParameter(
                    key: 'seller',
                    value: $this->profile,
                    type: UserProfileUid::TYPE,
                );
        }

        if($this->offer instanceof ProductOfferConst)
        {
            $orm
                ->andWhere('invariable.offer = :offer')
                ->setParameter(
                    key: 'offer',
                    value: $this->offer,
                    type: ProductOfferConst::TYPE,
                );
        }
        else
        {
            $orm->andWhere('invariable.offer IS NULL');
        }


        if($this->variation instanceof ProductVariationConst)
        {
            $orm
                ->andWhere('invariable.variation = :variation')
                ->setParameter(
                    key: 'variation',
                    value: $this->variation,
                    type: ProductVariationConst::TYPE,
                );
        }
        else
        {
            $orm->andWhere('invariable.variation IS NULL');
        }

        if($this->modification instanceof ProductModificationConst)
        {
            $orm
                ->andWhere('invariable.modification = :modification')
                ->setParameter(
                    key: 'modification',
                    value: $this->modification,
                    type: ProductModificationConst::TYPE,
                );
        }
        else
        {
            $orm->andWhere('invariable.modification IS NULL');
        }


        $orm->join(
            ProductSign::class,
            'main',
            'WITH',
            'main.id = invariable.main',
        );


        /** Получаем только если статус New «Новый» либо Return «Возврат» */
        $orm
            ->select('event')
            ->join(
                ProductSignEvent::class,
                'event',
                'WITH',
                '
                event.id = main.event AND 
                event.status IN (:status)
            ')
            ->setParameter(
                key: 'status',
                value: [ProductSignStatusNew::STATUS, ProductSignStatusReturn::STATUS],
                type: ArrayParameterType::STRING,
            );


        // Repository


        //        // (event.profile IS NULL OR event.profile = :profile) AND
        //        ->setParameter(
        //        key: 'profile',
        //        value: $this->profile,
        //        type: UserProfileUid::TYPE
        //    )

        $orm
            ->leftJoin(
                ProductSignModify::class,
                'modify',
                'WITH',
                'modify.event = main.event',
            );

        /**
         *  Сортировка по профилю:
         *  если у владельца имеется Честный знак - возвращает
         *  если нет у владельца - берет у партнера
         */
        $orm
            ->addOrderBy("CASE invariable.profile WHEN :profile THEN false ELSE true END")
            ->setParameter(
                key: 'profile',
                value: $this->profile,
                type: UserProfileUid::TYPE,
            );

        /** Сортируем по дате, выбирая самый старый знак и мешок */
        //$orm->addOrderBy('modify.modDate');
        $orm->addOrderBy('invariable.part');

        $orm->setMaxResults(1);

        return $orm->getOneOrNullResult() ?: false;
    }
}
