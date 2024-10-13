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

namespace BaksDev\Products\Sign\Repository\ProductSignByOrder;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Sign\Entity\Code\ProductSignCode;
use BaksDev\Products\Sign\Entity\Event\ProductSignEvent;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusProcess;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusDone;
use InvalidArgumentException;

final class ProductSignByOrder implements ProductSignByOrderInterface
{
    private OrderUid|false $order = false;

    private ProductSignStatus $status;

    public function __construct(private readonly DBALQueryBuilder $DBALQueryBuilder)
    {
        /** По умолчанию возвращаем знаки со статусом Process «В процессе» */
        $this->status = new ProductSignStatus(ProductSignStatusProcess::class);
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
     * По умолчанию возвращает знаки со статусом Process «В процессе»
     */
    public function execute(): array|false
    {
        if($this->order === false)
        {
            throw new InvalidArgumentException('Не передан обязательный параметр order через вызов метода ->forOrder(...)');
        }

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

        $dbal->join(
            'event',
            ProductSign::class,
            'main',
            'main.id = event.main'
        );

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
            ->addSelect("code.event AS code_event")
            ->addSelect("code.code AS code_string")
            ->leftJoin(
                'event',
                ProductSignCode::class,
                'code',
                'code.main = main.id'
            );


        return $dbal
            // ->enableCache('Namespace', 3600)
            ->fetchAllAssociative() ?: false;
    }
}
