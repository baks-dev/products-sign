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

namespace BaksDev\Products\Sign\UseCase\Admin\NewEdit\Code;

use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Sign\Entity\Code\ProductSignCodeInterface;
use BaksDev\Users\User\Type\Id\UserUid;
use chillerlan\QRCode\QRCode;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;

/** @see ProductSignCodeRepository */
final class ProductSignCodeDTO implements ProductSignCodeInterface
{
    /** QR-код */
    public ?File $file = null;

    /** Честный знак */
    #[Assert\NotBlank]
    private ?string $code = null;

    /** QR знака */
    #[Assert\NotBlank]
    private string $qr;

    /** Пользователь */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private UserUid $usr;

    /** ID продукта */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private ProductUid $product;

    /** Постоянный уникальный идентификатор ТП */
    #[Assert\Uuid]
    private ?ProductOfferConst $offer = null;

    /** Постоянный уникальный идентификатор варианта */
    #[Assert\Uuid]
    private ?ProductVariationConst $variation = null;

    /** Постоянный уникальный идентификатор модификации */
    #[Assert\Uuid]
    private ?ProductModificationConst $modification = null;

    /**
     * Code
     */
    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): self
    {
        $this->code = trim($code);
        $this->qr = (new QRCode())->render($this->code);

        return $this;
    }

    /**
     * Qr
     */
    public function getQr(): string
    {
        return $this->qr;
    }

    public function setQr(string $qr): self
    {
        $this->qr = $qr;
        return $this;
    }

    /**
     * Product
     */
    public function getProduct(): ProductUid
    {
        return $this->product;
    }

    public function setProduct(ProductUid $product): self
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
}