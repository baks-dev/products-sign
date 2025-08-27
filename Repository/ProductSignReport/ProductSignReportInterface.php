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

namespace BaksDev\Products\Sign\Repository\ProductSignReport;

use BaksDev\Delivery\Entity\Delivery;
use BaksDev\Delivery\Type\Id\DeliveryUid;
use BaksDev\Products\Category\Entity\CategoryProduct;
use BaksDev\Products\Category\Type\Id\CategoryProductUid;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use DateTimeImmutable;
use Generator;

interface ProductSignReportInterface
{
    public function fromProfile(UserProfile|UserProfileUid|string $profile): self;

    public function fromSeller(UserProfileUid|string $seller): self;

    public function dateFrom(DateTimeImmutable $from): self;

    public function dateTo(DateTimeImmutable $to): self;

    public function fromProductCategory(CategoryProduct|CategoryProductUid|null|false $category): self;

    public function orderType(Delivery|DeliveryUid|null|false $type): self;

    /**
     * Product
     */

    public function setProduct(ProductUid|string|null|false $product): self;

    public function setOffer(ProductOfferConst|string|null|false $offer): self;

    public function setVariation(ProductVariationConst|string|null|false $variation): self;

    public function setModification(ProductModificationConst|string|null|false $modification): self;


    public function onlyStatusDone(): self;

    public function onlyStatusProcess(): self;

    /**
     * Метод получает все реализованные честные знаки (для вывода из оборота)
     *
     * @return Generator<int, ProductSignReportResult>|false
     */
    public function findAll(): Generator|false;
}