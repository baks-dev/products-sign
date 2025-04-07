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

namespace BaksDev\Products\Sign\Messenger\ProductSignStatus\ProductSignDone;


use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;

final class ProductSignDoneMessage
{
    private string $order;

    private string $product;

    private string|false $offer;

    private string|false $variation;

    private string|false $modification;

    public function __construct(
        OrderUid|string $order,
        ProductUid|string $product,
        ProductOfferConst|string|null|false $offer,
        ProductVariationConst|string|null|false $variation,
        ProductModificationConst|string|null|false $modification,
    )
    {
        $this->order = (string) $order;
        $this->product = (string) $product;
        $this->offer = !empty($offer) ? (string) $offer : false;
        $this->variation = !empty($variation) ? (string) $variation : false;
        $this->modification = !empty($modification) ? (string) $modification : false;
    }

    /**
     * Order
     */
    public function getOrder(): OrderUid
    {
        return new OrderUid($this->order);
    }

    public function getProduct(): ProductUid
    {
        return new ProductUid($this->product);
    }

    public function getOfferConst(): ProductOfferConst|false
    {
        return $this->offer ? new ProductOfferConst($this->offer) : false;
    }

    public function getVariationConst(): ProductVariationConst|false
    {
        return $this->variation ? new ProductVariationConst($this->variation) : false;
    }

    public function getModificationConst(): ProductModificationConst|false
    {
        return $this->modification ? new ProductModificationConst($this->modification) : false;
    }
}