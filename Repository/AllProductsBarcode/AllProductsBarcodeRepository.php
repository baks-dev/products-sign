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

namespace BaksDev\Products\Sign\Repository\AllProductsBarcode;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Product\Entity\ProductInvariable;
use BaksDev\Products\Sign\Entity\Code\ProductSignCode;
use BaksDev\Products\Sign\Entity\Invariable\ProductSignInvariable;


final readonly class AllProductsBarcodeRepository implements AllProductsBarcodeInterface
{
    public function __construct(private DBALQueryBuilder $DBALQueryBuilder) {}

    /**
     * Метод возвращает все штрихкоды товаров
     */
    public function findAll(): array|bool
    {
        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $dbal->from(ProductInvariable::class, 'invariable');

        $dbal->leftOneJoin(
            'invariable',
            ProductSignInvariable::class,
            'sign_invariable',
            '
                sign_invariable.product = invariable.product AND
                sign_invariable.offer = invariable.offer AND
                sign_invariable.variation = invariable.variation AND
                sign_invariable.modification = invariable.modification
            ',
            'main',
        //$sort = null,
        //$desc = 'DESC'
        );

        $dbal
            ->addSelect('code.code')
            ->join(
                'sign_invariable',
                ProductSignCode::class,
                'code',
                'code.event = sign_invariable.event',
            );

        //        $dbal
        //            ->addSelect('invariable.product')
        //            ->addSelect('invariable.offer')
        //            ->addSelect('invariable.variation')
        //            ->addSelect('invariable.modification')
        //            ->from(ProductSignInvariable::class, 'invariable');

        return $dbal
            // ->enableCache('Namespace', 3600)
            ->fetchAllAssociative();
    }
}