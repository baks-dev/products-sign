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

namespace BaksDev\Products\Sign\Repository\ExportProductSignByStatus;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Product\Entity\Offers\ProductOffer;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\ProductModification;
use BaksDev\Products\Product\Entity\Offers\Variation\ProductVariation;
use BaksDev\Products\Product\Entity\Product;
use BaksDev\Products\Product\Entity\Trans\ProductTrans;
use BaksDev\Products\Sign\Entity\Code\ProductSignCode;
use BaksDev\Products\Sign\Entity\Event\ProductSignEvent;
use BaksDev\Products\Sign\Entity\Invariable\ProductSignInvariable;
use BaksDev\Products\Sign\Entity\Modify\ProductSignModify;
use BaksDev\Products\Sign\Entity\ProductSign;
use Doctrine\DBAL\ArrayParameterType;
use Generator;


final class ExportProductSignByStatusRepository implements ExportProductSignByStatusInterface
{
    private string|array $status = [];

    public function __construct(private readonly DBALQueryBuilder $DBALQueryBuilder) {}


    public function status(string|array $status): self
    {
        if(is_string($status))
        {
            if($status === 'new')
            {
                $this->status = ['new', 'return'];
                return $this;
            }

            $status = [$status];
        }

        $this->status = $status;

        return $this;
    }

    /**
     * Метод возвращает все кизы в указанном статусе
     *
     * @return Generator<ExportProductSignByStatusResult>|false
     */
    public function findAll(): Generator|bool
    {
        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        $dbal->from(
            ProductSignInvariable::class,
            'product_sign_invariable',
        );

        $dbal
            ->join(
                'product_sign_invariable',
                ProductSign::class,
                'product_sign',
                'product_sign.id = product_sign_invariable.main',
            );

        $dbal
            ->addSelect('product_sign_event.status')
            ->join(
                'product_sign_invariable',
                ProductSignEvent::class,
                'product_sign_event',
                '
                    product_sign_event.id = product_sign_invariable.event
                    AND product_sign_event.status IN (:status)
                ')
            ->setParameter(
                key: 'status',
                value: $this->status,
                type: ArrayParameterType::STRING,
            );

        $dbal
            ->addSelect('product_sign_code.code')
            ->join(
                'product_sign_invariable',
                ProductSignCode::class,
                'product_sign_code',
                'product_sign_code.main = product_sign_invariable.main',

            );


        $dbal
            ->join(
                'product_sign_invariable',
                ProductSignModify::class,
                'product_sign_modify',
                'product_sign_modify.event = product_sign_invariable.event',
            );


        $dbal
            ->join(
                'product_sign_invariable',
                Product::class,
                'product',
                'product.id = product_sign_invariable.product',
            );

        $dbal
            ->leftJoin(
                'product_sign_invariable',
                ProductOffer::class,
                'product_offer',
                'product_offer.event = product.event 
                AND product_offer.const = product_sign_invariable.offer',
            );


        $dbal
            ->leftJoin(
                'product_sign_invariable',
                ProductVariation::class,
                'product_variation',
                'product_variation.offer = product_offer.id
                AND product_variation.const = product_sign_invariable.variation',
            );

        $dbal
            ->leftJoin(
                'product_sign_invariable',
                ProductModification::class,
                'product_modification',
                'product_modification.variation = product_variation.id
              AND product_modification.const = product_sign_invariable.modification',
            );

        $dbal
            ->addSelect("
                CONCAT(
                   product_trans.name, ' ',
                   product_variation.value, ' ',
                   product_modification.value, ' ',
                   product_offer.value
               ) AS product
            ")
            ->join(
                'product',
                ProductTrans::class,
                'product_trans',
                'product_trans.event = product.event AND product_trans.local = :local',
            );


        $return = $dbal
            // ->enableCache('Namespace', 3600)
            ->fetchAllHydrate(ExportProductSignByStatusResult::class);

        $this->status = [];

        return $return;
    }
}