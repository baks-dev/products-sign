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

namespace BaksDev\Products\Sign\Repository\CurrentEvent;

use BaksDev\Core\Doctrine\ORMQueryBuilder;
use BaksDev\Products\Sign\Entity\Event\ProductSignEvent;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Type\Id\ProductSignUid;

final class ProductSignCurrentEventRepository implements ProductSignCurrentEventInterface
{
    private ORMQueryBuilder $ORMQueryBuilder;

    public function __construct(ORMQueryBuilder $ORMQueryBuilder)
    {
        $this->ORMQueryBuilder = $ORMQueryBuilder;
    }

    /**
     * Возвращает активное событие
     */
    public function findProductSignEvent(ProductSign|ProductSignUid|string $sign): ?ProductSignEvent
    {
        $sign = is_string($sign) ? new ProductSignUid($sign) : $sign;
        $sign = $sign instanceof ProductSign ? $sign->getId() : $sign;

        $qb = $this->ORMQueryBuilder->createQueryBuilder(self::class);

        $qb->select('event');

        $qb
            ->from(ProductSign::class, 'main')
            ->where('main.id = :main')
            ->setParameter('main', $sign, ProductSignUid::TYPE);

        $qb->join(
            ProductSignEvent::class,
            'event',
            'WITH',
            'event.id = main.event'
        );

        return $qb->getOneOrNullResult();
    }
}