<?php
/*
 *  Copyright 2026.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Products\Sign\UseCase\Admin\Status\Return\Invariable;

use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Sign\Entity\Invariable\ProductSignInvariableInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use ReflectionProperty;
use Symfony\Component\Validator\Constraints as Assert;

/** @see ProductSignInvariable */
final class ReturnProductSignInvariableDTO implements ProductSignInvariableInterface
{
    /** Группа штрихкодов (для групповой отмены либо списания) */
    #[Assert\NotBlank]
    private string $part;

    /**
     * Продавец честного пользователя
     */
    #[Assert\Uuid]
    private readonly ?UserProfileUid $seller;

    /** ID продукта */
    #[Assert\Uuid]
    private ?ProductUid $product;

    /**
     * Part
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


    public function getSeller(): ?UserProfileUid
    {
        if(false === (new ReflectionProperty(self::class, 'seller')->isInitialized($this)))
        {
            return null;
        }

        return $this->seller;
    }

    public function setSeller(?UserProfileUid $seller): self
    {
        if(false === ($seller instanceof UserProfileUid))
        {
            return $this;
        }

        if(false === (new ReflectionProperty(self::class, 'seller')->isInitialized($this)))
        {
            $this->seller = $seller;
        }

        return $this;
    }

    public function setNullSeller(): self
    {
        $this->seller = null;
        return $this;
    }

    public function getProduct(): ?ProductUid
    {
        return $this->product;
    }

    public function setProduct(?ProductUid $product): self
    {
        $this->product = $product;
        return $this;
    }



}
