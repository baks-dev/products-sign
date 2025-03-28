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

namespace BaksDev\Products\Sign\Repository\ProductSignProcessByOrderProduct;

use BaksDev\Core\Doctrine\ORMQueryBuilder;
use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Product\Entity\Product;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Sign\Entity\Event\ProductSignEvent;
use BaksDev\Products\Sign\Entity\Invariable\ProductSignInvariable;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusProcess;
use InvalidArgumentException;

final class ProductSignProcessByOrderProductRepository implements ProductSignProcessByOrderProductInterface
{

    private ProductUid|false $product = false;

    private OrderUid|false $order = false;

    private ProductOfferConst|false $offer = false;

    private ProductVariationConst|false $variation = false;

    private ProductModificationConst|false $modification = false;


    public function __construct(private ORMQueryBuilder $ORMQueryBuilder) {}

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

    public function forProduct(Product|ProductUid|string $product): self
    {
        if(empty($product))
        {
            $this->product = false;
            return $this;
        }

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

    /**
     * Метод возвращает Честный знак на продукцию по заказу со статусом Process «В процессе»
     */
    public function find(): ProductSignEvent|false
    {
        if(false === ($this->order instanceof OrderUid))
        {
            throw new InvalidArgumentException('Invalid Argument Order');
        }

        if(false === ($this->product instanceof ProductUid))
        {
            throw new InvalidArgumentException('Не определен обязательный параметр order');
        }

        $orm = $this->ORMQueryBuilder->createQueryBuilder(self::class);

        $orm
            ->select('event')
            ->from(ProductSignEvent::class, 'event');

        $orm
            ->where('event.ord = :ord')
            ->setParameter(
                key: 'ord',
                value: $this->order,
                type: OrderUid::TYPE
            );

        $orm
            ->andWhere('event.status = :status')
            ->setParameter(
                key: 'status',
                value: ProductSignStatusProcess::class,
                type: ProductSignStatus::TYPE
            );

        $orm->join(
            ProductSign::class,
            'main',
            'WITH',
            'main.event = event.id'
        );

        $orm
            ->join(
                ProductSignInvariable::class,
                'invariable',
                'WITH',
                'invariable.event = event.id AND invariable.product = :product'
            )
            ->setParameter(
                key: 'product',
                value: $this->product,
                type: ProductUid::TYPE
            );


        if($this->offer instanceof ProductOfferConst)
        {
            $orm
                ->andWhere('invariable.offer = :offer')
                ->setParameter(
                    key: 'offer',
                    value: $this->offer,
                    type: ProductOfferConst::TYPE
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
                    key: 'variation',
                    value: $this->variation,
                    type: ProductVariationConst::TYPE
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
                    key: 'modification',
                    value: $this->modification,
                    type: ProductModificationConst::TYPE
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
