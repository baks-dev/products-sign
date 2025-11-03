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
 *
 */

declare(strict_types=1);

namespace BaksDev\Products\Sign\UseCase\Admin\New\Invariable;

use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Sign\Entity\Invariable\ProductSignInvariableInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Type\Id\UserUid;
use Symfony\Component\Validator\Constraints as Assert;

/** @see ProductSignInvariable */
final class ProductSignInvariableDTO implements ProductSignInvariableInterface
{
    /** Пользователь */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private UserUid $usr;

    /**
     * Владелец честного пользователя
     */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private ?UserProfileUid $profile = null;

    /**
     * Продавец честного пользователя
     */
    private ?UserProfileUid $seller = null;


    /** Группа штрихкодов для отмены  */
    #[Assert\NotBlank]
    private string $part;

    /** Грузовая таможенная декларация (номер) */
    private ?string $number = null;

    /**
     * Продукт
     */

    /** ID продукта */
    #[Assert\Uuid]
    private ?ProductUid $product;

    /** Постоянный уникальный идентификатор ТП */
    #[Assert\Uuid]
    private ?ProductOfferConst $offer;

    /** Постоянный уникальный идентификатор варианта */
    #[Assert\Uuid]
    private ?ProductVariationConst $variation;

    /** Постоянный уникальный идентификатор модификации */
    #[Assert\Uuid]
    private ?ProductModificationConst $modification;

    /**
     * Usr
     */
    public function getUsr(): UserUid
    {
        return $this->usr;
    }

    public function setUsr(UserUid $usr): self
    {
        $this->usr = $usr;
        return $this;
    }

    /**
     * Group
     */
    public function getPart(): string
    {
        return $this->part;
    }

    public function setPart(mixed $part): self
    {
        $this->part = (string) $part;
        return $this;
    }

    /**
     * Product
     */
    public function getProduct(): ?ProductUid
    {
        return $this->product;
    }

    public function setProduct(?ProductUid $product): self
    {
        $this->product = $product;
        return $this;
    }

    /**
     * Offer
     */
    public function getOffer(): ?ProductOfferConst
    {
        return $this->offer;
    }

    public function setOffer(?ProductOfferConst $offer): self
    {
        $this->offer = $offer;
        return $this;
    }

    /**
     * Variation
     */
    public function getVariation(): ?ProductVariationConst
    {
        return $this->variation;
    }

    public function setVariation(?ProductVariationConst $variation): self
    {
        $this->variation = $variation;
        return $this;
    }

    /**
     * Modification
     */
    public function getModification(): ?ProductModificationConst
    {
        return $this->modification;
    }

    public function setModification(?ProductModificationConst $modification): self
    {
        $this->modification = $modification;
        return $this;
    }

    /**
     * Number
     */
    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(?string $number): self
    {
        $this->number = $number;
        return $this;
    }

    /**
     * Container
     */
    public function getContainer(): ?string
    {
        return $this->container;
    }

    public function setContainer(?string $container): self
    {
        $this->container = $container;
        return $this;
    }

    /**
     * Profile
     */
    public function getProfile(): ?UserProfileUid
    {
        return $this->profile;
    }

    public function setProfile(?UserProfileUid $profile): self
    {
        $this->profile = $profile;
        return $this;
    }

    /**
     * Seller
     */
    public function getSeller(): ?UserProfileUid
    {
        return $this->seller;
    }

    public function setSeller(?UserProfileUid $seller): self
    {
        $this->seller = $seller;
        return $this;
    }
}
