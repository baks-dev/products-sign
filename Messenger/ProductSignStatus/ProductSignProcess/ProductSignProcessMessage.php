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

namespace BaksDev\Products\Sign\Messenger\ProductSignStatus\ProductSignProcess;


use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Sign\Type\Id\ProductSignUid;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Type\Id\UserUid;

final readonly class ProductSignProcessMessage
{
    private string $order;
    private string $part;
    private string $user;
    private string $profile;
    private string $product;

    private ?string $offer;
    private ?string $variation;
    private ?string $modification;

    public function __construct(
        OrderUid|string $order,
        ProductSignUid|string $part,
        UserUid|string $user,
        UserProfileUid|string $profile,
        ProductUid|string $product,

        ProductOfferConst|string|null|false $offer,
        ProductVariationConst|string|null|false $variation,
        ProductModificationConst|string|null|false $modification,
    )
    {
        $this->part = (string) $part;
        $this->user = (string) $user;
        $this->profile = (string) $profile;
        $this->product = (string) $product;

        $this->offer = $offer ? (string) $offer : null;
        $this->variation = $variation ? (string) $variation : null;
        $this->modification = $modification ? (string) $modification : null;
        $this->order = $order;
    }

    /**
     * Order
     */
    public function getOrder(): OrderUid
    {
        return new OrderUid($this->order);
    }

    /**
     * Part
     */
    public function getPart(): ProductSignUid
    {
        return new ProductSignUid($this->part);
    }

    /**
     * User
     */
    public function getUser(): UserUid
    {
        return new UserUid($this->user);
    }

    /**
     * Profile
     */
    public function getProfile(): UserProfileUid
    {
        return new UserProfileUid($this->profile);
    }

    /**
     * Product
     */
    public function getProduct(): ProductUid
    {
        return new ProductUid($this->product);
    }

    /**
     * Offer
     */
    public function getOffer(): ?ProductOfferConst
    {
        return $this->offer ? new ProductOfferConst($this->offer) : null;
    }

    /**
     * Variation
     */
    public function getVariation(): ?ProductVariationConst
    {
        return $this->variation ? new ProductVariationConst($this->variation) : null;
    }

    /**
     * Modification
     */
    public function getModification(): ?ProductModificationConst
    {
        return $this->modification ? new ProductModificationConst($this->modification) : null;
    }

}