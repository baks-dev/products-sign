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

namespace BaksDev\Products\Sign\Repository\ProductSignProcessByOrderProduct;

use BaksDev\Core\Doctrine\ORMQueryBuilder;
use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Sign\Entity\Code\ProductSignCode;
use BaksDev\Products\Sign\Entity\Event\ProductSignEvent;
use BaksDev\Products\Sign\Entity\Invariable\ProductSignInvariable;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusProcess;
use InvalidArgumentException;

final class ProductSignProcessByOrderProductRepository implements ProductSignProcessByOrderProductInterface
{
    private ORMQueryBuilder $ORMQueryBuilder;

    private OrderUid $order;

    private ?ProductOfferConst $offer = null;

    private ?ProductVariationConst $variation = null;

    private ?ProductModificationConst $modification = null;

    public function __construct(ORMQueryBuilder $ORMQueryBuilder)
    {
        $this->ORMQueryBuilder = $ORMQueryBuilder;
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
     * Метод возвращает Честный знак на продукцию по заказу со статусом Process «В процессе»
     */
    public function find(): ProductSignEvent|false
    {
        if(!isset($this->order))
        {
            throw new InvalidArgumentException('Не определен обязательный параметр order');
        }

        $orm = $this->ORMQueryBuilder->createQueryBuilder(self::class);

        $orm->select('event');

        $orm->from(ProductSignEvent::class, 'event');

        $orm
            ->where('event.ord = :ord')
            ->setParameter(
                'ord',
                $this->order,
                OrderUid::TYPE
            );

        $orm
            ->andWhere('event.status = :status')
            ->setParameter(
                'status',
                ProductSignStatusProcess::class,
                ProductSignStatus::TYPE
            );

        $orm->join(
            ProductSign::class,
            'main',
            'WITH',
            'main.event = event.id'
        );

        $orm->join(
            ProductSignInvariable::class,
            'invariable',
            'WITH',
            'invariable.event = event.id'
        );


        if($this->offer instanceof ProductOfferConst)
        {
            $orm
                ->andWhere('invariable.offer = :offer')
                ->setParameter(
                    'offer',
                    $this->offer,
                    ProductOfferConst::TYPE
                );
        }
        else
        {
            $orm->andWhere('invariable.offer IS NULL');
        }

        if($this->variation)
        {
            $orm
                ->andWhere('invariable.variation = :variation')
                ->setParameter(
                    'variation',
                    $this->variation,
                    ProductVariationConst::TYPE
                );

        }
        else
        {
            $orm->andWhere('invariable.variation IS NULL');
        }


        if($this->modification)
        {
            $orm
                ->andWhere('invariable.modification = :modification')
                ->setParameter(
                    'modification',
                    $this->modification,
                    ProductModificationConst::TYPE
                );

        }
        else
        {
            $orm->andWhere('invariable.modification IS NULL');
        }

        $orm->setMaxResults(1);

        return $orm->getQuery()->getOneOrNullResult() ?: false;
    }
}
