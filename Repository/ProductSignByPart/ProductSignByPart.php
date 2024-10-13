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

namespace BaksDev\Products\Sign\Repository\ProductSignByPart;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Sign\Entity\Code\ProductSignCode;
use BaksDev\Products\Sign\Entity\Event\ProductSignEvent;
use BaksDev\Products\Sign\Entity\Invariable\ProductSignInvariable;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Type\Id\ProductSignUid;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusDecommission;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusProcess;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusDone;
use InvalidArgumentException;

final class ProductSignByPart implements ProductSignByPartInterface
{
    private ProductSignUid|false $part = false;

    private ProductSignStatus $status;

    public function __construct(private readonly DBALQueryBuilder $DBALQueryBuilder)
    {
        /** По умолчанию возвращаем знаки со статусом Decommission «Списано» */
        $this->status = new ProductSignStatus(ProductSignStatusDecommission::class);
    }

    public function forPart(ProductSignUid|string $order): self
    {
        if(is_string($order))
        {
            $order = new ProductSignUid($order);
        }

        $this->part = $order;

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
     * Метод возвращает все штрихкоды «Честный знак» для печати по идентификатору артии
     * По умолчанию возвращает знаки со статусом Process «В процессе»
     */
    public function execute(): array|false
    {
        if($this->part === false)
        {
            throw new InvalidArgumentException('Не передан обязательный параметр order через вызов метода ->forPart(...)');
        }

        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $dbal->from(
            ProductSignInvariable::class,
            'invariable'
        );

        $dbal
            ->where('invariable.part = :part')
            ->setParameter('part', $this->part, ProductSignUid::TYPE);


        $dbal->join(
            'invariable',
            ProductSign::class,
            'main',
            'main.id = invariable.main'
        );


        $dbal
            ->join(
                'invariable',
                ProductSignEvent::class,
                'event',
                'event.id = invariable.event AND event.status = :status'
            )
            ->setParameter(
                'status',
                $this->status,
                ProductSignStatus::TYPE
            );


        $dbal
            ->addSelect("
                CASE
                   WHEN code.name IS NOT NULL 
                   THEN CONCAT ( '/upload/".$dbal->table(ProductSignCode::class)."' , '/', code.name)
                   ELSE NULL
                END AS code_image
            ")
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
