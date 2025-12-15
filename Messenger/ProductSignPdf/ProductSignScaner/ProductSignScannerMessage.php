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

namespace BaksDev\Products\Sign\Messenger\ProductSignPdf\ProductSignScaner;


use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Type\Id\UserUid;
use Symfony\Component\Validator\Constraints as Assert;

final class ProductSignScannerMessage
{

    #[Assert\NotBlank]
    #[Assert\Uuid]
    private string $usr;

    #[Assert\Uuid]
    private ?string $profile;

    /** ID продукта */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private readonly string $product;

    /** Постоянный уникальный идентификатор ТП */
    #[Assert\Uuid]
    private readonly ?string $offer;

    /** Постоянный уникальный идентификатор варианта */
    #[Assert\Uuid]
    private readonly ?string $variation;

    /** Постоянный уникальный идентификатор модификации */
    #[Assert\Uuid]
    private readonly ?string $modification;

    /** Доступен только владельцу */
    private bool $share;

    /** Грузовая таможенная декларация (номер) */
    private ?string $number;

    public function __construct(
        private readonly string $path,
        private readonly mixed $part,
        UserUid $usr,
        ?UserProfileUid $profile,
        ProductUid $product,
        ?ProductOfferConst $offer,
        ?ProductVariationConst $variation,
        ?ProductModificationConst $modification,
        bool $share,
        ?string $number,
        private readonly bool $isNew
    )
    {
        $this->usr = (string) $usr;
        $this->profile = (string) $profile;
        $this->product = (string) $product;

        $this->offer = $offer ? (string) $offer : null;
        $this->variation = $variation ? (string) $variation : null;
        $this->modification = $modification ? (string) $modification : null;

        $this->share = $share;
        $this->number = $number;
    }

    /**
     * Usr
     */
    public function getUsr(): UserUid
    {
        return new UserUid($this->usr);
    }

    /**
     * Profile
     */
    public function getProfile(): ?UserProfileUid
    {
        return $this->profile ? new UserProfileUid($this->profile) : null;
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

    /**
     * Number
     */
    public function getNumber(): ?string
    {
        return $this->number;
    }

    /**
     * Share
     */
    public function isNotShare(): bool
    {
        return $this->share;
    }


    public function getRealPath(): string
    {
        return $this->path;
    }

    public function getPart(): string
    {
        return (string) $this->part;
    }

    public function isNew(): bool
    {
        return $this->isNew;
    }
}
