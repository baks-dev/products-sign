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

namespace BaksDev\Products\Sign\UseCase\Admin\Decommission;

use BaksDev\Products\Category\Type\Id\CategoryProductUid;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Type\Id\UserUid;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;

final class DecommissionProductSignDTO
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private UserUid $usr;

    /**
     * Профиль пользователя (null - общий)
     */
    #[Assert\Uuid]
    #[Assert\NotBlank]
    private ?UserProfileUid $profile = null;

    /** Категория */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private ?CategoryProductUid $category = null;

    /** ID продукта */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private ?ProductUid $product = null;

    /** Постоянный уникальный идентификатор ТП */
    #[Assert\Uuid]
    private ?ProductOfferConst $offer = null;

    /** Постоянный уникальный идентификатор варианта */
    #[Assert\Uuid]
    private ?ProductVariationConst $variation = null;

    /** Постоянный уникальный идентификатор модификации */
    #[Assert\Uuid]
    private ?ProductModificationConst $modification = null;

    /** Постоянный уникальный идентификатор модификации */
    #[Assert\NotBlank]
    private ?int $total = null;


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
     * Category
     */
    public function getCategory(): ?CategoryProductUid
    {
        return $this->category;
    }

    public function setCategory(?CategoryProductUid $category): self
    {
        $this->category = $category;
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
     * Total
     */
    public function getTotal(): ?int
    {
        return $this->total;
    }

    public function setTotal(?int $total): self
    {
        $this->total = $total;
        return $this;
    }

}
