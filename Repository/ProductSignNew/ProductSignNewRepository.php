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

namespace BaksDev\Products\Sign\Repository\ProductSignNew;

use BaksDev\Core\Doctrine\ORMQueryBuilder;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Product\Entity\Product;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Sign\Entity\Code\ProductSignCode;
use BaksDev\Products\Sign\Entity\Event\ProductSignEvent;
use BaksDev\Products\Sign\Entity\Invariable\ProductSignInvariable;
use BaksDev\Products\Sign\Entity\Modify\ProductSignModify;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusNew;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Entity\User;
use BaksDev\Users\User\Type\Id\UserUid;
use InvalidArgumentException;

final class ProductSignNewRepository implements ProductSignNewInterface
{
    private ORMQueryBuilder $ORMQueryBuilder;

    private UserUid $user;

    private UserProfileUid $profile;

    private ProductUid $product;

    private ?ProductOfferConst $offer = null;

    private ?ProductVariationConst $variation = null;

    private ?ProductModificationConst $modification = null;

    public function __construct(ORMQueryBuilder $ORMQueryBuilder)
    {
        $this->ORMQueryBuilder = $ORMQueryBuilder;
    }

    public function forUser(User|UserUid|string $user): self
    {
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

    public function forOfferConst(ProductOfferConst|string|null $offer): self
    {
        if($offer === null)
        {
            return $this;
        }

        if(is_string($offer))
        {
            $offer = new ProductOfferConst($offer);
        }

        $this->offer = $offer;

        return $this;
    }

    public function forVariationConst(ProductVariationConst|string|null $variation): self
    {
        if($variation === null)
        {
            return $this;
        }

        if(is_string($variation))
        {
            $variation = new ProductVariationConst($variation);
        }

        $this->variation = $variation;

        return $this;
    }

    public function forModificationConst(ProductModificationConst|string|null $modification): self
    {
        if($modification === null)
        {
            return $this;
        }

        if(is_string($modification))
        {
            $modification = new ProductModificationConst($modification);
        }

        $this->modification = $modification;

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
            ->setParameter('usr', $this->user, UserUid::TYPE);


        $orm
            ->andWhere('invariable.product = :product')
            ->setParameter('product', $this->product, ProductUid::TYPE);


        if($this->offer)
        {
            $orm
                ->andWhere('invariable.offer = :offer OR invariable.offer IS NULL')
                ->setParameter('offer', $this->offer, ProductOfferConst::TYPE);
        }
        else
        {
            $orm->andWhere('invariable.offer IS NULL');
        }


        if($this->variation)
        {
            $orm
                ->andWhere('invariable.variation = :variation')
                ->setParameter('variation', $this->variation, ProductVariationConst::TYPE);
        }
        else
        {
            $orm->andWhere('invariable.variation IS NULL');
        }

        if($this->modification)
        {
            $orm
                ->andWhere('invariable.modification = :modification')
                ->setParameter('modification', $this->modification, ProductModificationConst::TYPE);
        }
        else
        {
            $orm->andWhere('invariable.modification IS NULL');
        }


        $orm->join(
            ProductSign::class,
            'main',
            'WITH',
            'main.id = invariable.main'
        );

        $orm
            ->select('event')
            ->join(
                ProductSignEvent::class,
                'event',
                'WITH',
                '
                event.id = main.event AND 
                (event.profile IS NULL OR event.profile = :profile) AND
                event.status = :status
            '
            )
            ->setParameter(
                'profile',
                $this->profile,
                UserProfileUid::TYPE
            )
            ->setParameter(
                'status',
                ProductSignStatusNew::class,
                ProductSignStatus::TYPE
            );

        $orm
            ->leftJoin(
                ProductSignModify::class,
                'modify',
                'WITH',
                'modify.event = main.event'
            );


        /** Сортируем по дате, выбирая самый старый знак */
        $orm->orderBy('event.profile');
        $orm->addOrderBy('modify.modDate');

        $orm->setMaxResults(1);

        return $orm->getOneOrNullResult() ?: false;
    }


}
