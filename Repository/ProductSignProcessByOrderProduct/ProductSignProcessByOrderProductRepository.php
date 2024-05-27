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
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Sign\Entity\Code\ProductSignCode;
use BaksDev\Products\Sign\Entity\Event\ProductSignEvent;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusProcess;

final class ProductSignProcessByOrderProductRepository implements ProductSignProcessByOrderProductInterface
{
    private ORMQueryBuilder $ORMQueryBuilder;

    public function __construct(ORMQueryBuilder $ORMQueryBuilder)
    {
        $this->ORMQueryBuilder = $ORMQueryBuilder;
    }

    /**
     * Метод возвращает Честный знак на продукцию по заказу со статусом Process «В процессе»
     */
    public function getProductSign(
        OrderUid $order,
        ?ProductOfferConst $offer,
        ?ProductVariationConst $variation,
        ?ProductModificationConst $modification
    ): ?ProductSignEvent
    {
        $orm = $this->ORMQueryBuilder->createQueryBuilder(self::class);

        $orm->select('event');

        $orm
            ->from(ProductSignEvent::class, 'event')
            ->where('event.ord = :ord')
            ->setParameter('ord', $order, OrderUid::TYPE)
            ->andWhere('event.status = :status')
            ->setParameter('status', new ProductSignStatus(ProductSignStatusProcess::class), ProductSignStatus::TYPE);

        $orm->join(
            ProductSign::class,
            'main',
            'WITH',
            'main.event = event.id'
        );

        $orm->join(
            ProductSignCode::class,
            'code',
            'WITH',
            '
            code.event = event.id AND 
            code.offer = :offer AND
            code.variation = :variation AND
            code.modification = :modification 
            '
        );

        $orm->setParameter('offer', $offer);
        $orm->setParameter('variation', $variation);
        $orm->setParameter('modification', $modification);

        $orm->setMaxResults(1);

        return $orm->getQuery()->getOneOrNullResult();
    }
}