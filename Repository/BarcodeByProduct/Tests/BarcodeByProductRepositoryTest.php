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

namespace BaksDev\Products\Sign\Repository\BarcodeByProduct\Tests;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Product\Type\Barcode\ProductBarcode;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Sign\Repository\BarcodeByProduct\BarcodeByProductInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DependsOnClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;


#[Group('products-sign')]
#[When(env: 'test')]
class BarcodeByProductRepositoryTest extends KernelTestCase
{

    public function testUseCase(): void
    {
        /** @var BarcodeByProductInterface $BarcodeByProductRepository */
        $BarcodeByProductRepository = self::getContainer()->get(BarcodeByProductInterface::class);

        $ProductBarcode = $BarcodeByProductRepository
            ->forProduct(new ProductUid('01876b34-ed23-7c18-ba48-9071e8646a08'))
            ->forOfferConst(new ProductOfferConst('01876b34-eccb-7188-887f-0738cae05232'))
            ->forVariationConst(new ProductVariationConst('01876b34-ecce-7c46-9f63-fc184b6527ee'))
            ->forModificationConst(new ProductModificationConst('01876b34-ecd2-762c-9834-b6a914a020ba'))
            ->find();

        // dd($ProductBarcode);

        self::assertTrue($ProductBarcode instanceof ProductBarcode || $ProductBarcode === false);

    }
}