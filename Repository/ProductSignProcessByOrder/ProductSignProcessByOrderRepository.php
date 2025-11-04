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

namespace BaksDev\Products\Sign\Repository\ProductSignProcessByOrder;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Sign\Entity\Event\ProductSignEvent;
use BaksDev\Products\Sign\Entity\Invariable\ProductSignInvariable;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Type\Event\ProductSignEventUid;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusProcess;
use Generator;
use InvalidArgumentException;

final class ProductSignProcessByOrderRepository implements ProductSignProcessByOrderInterface
{
    private OrderUid|false $order = false;

    private bool $onlyProcess = false;

    public function __construct(private readonly DBALQueryBuilder $DBALQueryBuilder) {}

    public function forOrder(Order|OrderUid|string $order): self
    {
        if(empty($order))
        {
            $this->order = false;
            return $this;
        }

        if(is_string($order))
        {
            $order = new OrderUid($order);
        }

        if($order instanceof Order)
        {
            $order = $order->getId();
        }

        $this->order = $order;

        return $this;
    }

    public function onlyStatusProcess(): void
    {
        $this->onlyProcess = true;
    }


    /**
     * Метод возвращает события всех Честных знаков по заказу со статусом Process «В резерве»
     *
     * @return Generator<ProductSignEventUid>|false
     */
    public function findAll(): Generator|false
    {
        if(false === ($this->order instanceof OrderUid))
        {
            throw new InvalidArgumentException('Invalid Argument Order');
        }

        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $dbal
            ->select('event.id AS value')
            ->from(ProductSignEvent::class, 'event');

        $dbal->join(
            'event',
            ProductSignInvariable::class,
            'invariable',
            'invariable.event = event.id'
        );

        $dbal
            ->where('event.ord = :ord')
            ->setParameter(
                'ord',
                $this->order,
                OrderUid::TYPE
            );

        if(true === $this->onlyProcess)
        {
            $dbal
                ->andWhere('event.status = :status')
                ->setParameter(
                    'status',
                    ProductSignStatusProcess::class,
                    ProductSignStatus::TYPE,
                );
        }

        $dbal
            ->join(
                'event',
                ProductSign::class,
                'main',
                'main.event = event.id'
            );

        $dbal->addSelect("JSON_AGG
            ( DISTINCT
			
			    JSONB_BUILD_OBJECT
			    (
			        'id', main.id,
			        'product', invariable.product,
			        'offer', invariable.offer,
			        'variation', invariable.variation,
			        'modification', invariable.modification
			    )
			) AS params
        ");

        $dbal->allGroupByExclude();

        return $dbal->fetchAllHydrate(ProductSignEventUid::class);
    }
}