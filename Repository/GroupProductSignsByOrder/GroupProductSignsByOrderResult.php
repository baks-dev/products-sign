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

namespace BaksDev\Products\Sign\Repository\GroupProductSignsByOrder;

use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;

final readonly class GroupProductSignsByOrderResult
{

    public function __construct(
        private int $counter, // " => 100
        private string $sign_part, // " => "019643c2-9025-7050-b731-95ce5a4cbc77"

        private string $product_id, // " => "0194d10e-d18b-7ee9-9bbb-0ba83582342c"
        private string $product_name, // " => "Футболка короткий рукав"

        private ?string $product_offer_const, // " => "0194d10e-ce65-7dd2-97a2-0608f2683361"
        private ?string $product_offer_value, // " => "FFFFFF"
        private ?string $product_offer_reference, // " => "color_type"
        private ?string $product_offer_postfix, // " => "color_type"

        private ?string $product_variation_const, // " => "0194d10e-cf53-77e1-90dc-8342dd45b543"
        private ?string $product_variation_value, // " => "3XL"
        private ?string $product_variation_reference, // " => "size_clothing_type"
        private ?string $product_variation_postfix, // " => "size_clothing_type"

        private ?string $product_modification_const, // " => null
        private ?string $product_modification_value, // " => null
        private ?string $product_modification_reference, // " => null
        private ?string $product_modification_postfix, // " => null

        private string $product_article, // " => "T-WHITE-3XL"
    ) {}

    public function getCounter(): int
    {
        return $this->counter;
    }

    public function getSignPart(): string
    {
        return $this->sign_part;
    }

    public function getProductId(): ProductUid
    {
        return new ProductUid($this->product_id);
    }


    public function getProductName(): string
    {
        return $this->product_name;
    }

    public function getProductArticle(): string
    {
        return $this->product_article;
    }

    /**
     * Offer
     */

    public function getProductOfferConst(): ?ProductOfferConst
    {
        return $this->product_offer_const ? new ProductOfferConst($this->product_offer_const) : null;
    }

    public function getProductOfferValue(): ?string
    {
        return $this->product_offer_value ?: null;
    }

    public function getProductOfferReference(): ?string
    {
        return $this->product_offer_reference ?: null;
    }

    public function getProductOfferPostfix(): ?string
    {
        return $this->product_offer_postfix;
    }


    /**
     * Variation
     */

    public function getProductVariationConst(): ?ProductVariationConst
    {
        return $this->product_variation_const ? new ProductVariationConst($this->product_variation_const) : null;
    }

    public function getProductVariationValue(): ?string
    {
        return $this->product_variation_value ?: null;
    }

    public function getProductVariationReference(): ?string
    {
        return $this->product_variation_reference ?: null;
    }

    public function getProductVariationPostfix(): ?string
    {
        return $this->product_variation_postfix;
    }


    /**
     * Modification
     */

    public function getProductModificationConst(): ?ProductModificationConst
    {
        return $this->product_modification_const ? new ProductModificationConst($this->product_modification_const) : null;
    }

    public function getProductModificationValue(): ?string
    {
        return $this->product_modification_value ?: null;
    }

    public function getProductModificationReference(): ?string
    {
        return $this->product_modification_reference ?: null;
    }

    public function getProductModificationPostfix(): ?string
    {
        return $this->product_modification_postfix;
    }
}