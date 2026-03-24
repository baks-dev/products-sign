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
 *
 */

declare(strict_types=1);

namespace BaksDev\Products\Sign\UseCase\Admin\Pdf;

use BaksDev\Products\Sign\UseCase\Admin\Pdf\ProductSignFile\ProductSignFileDTO;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Type\Id\UserUid;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Объект для загрузки Честных знаков без привязки к продукту
 */
final class AddProductSignPdfDTO implements ProductSignPdfInterface
{
    /** Пользователь */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private UserUid $usr;

    /** Профиль пользователя (Владельца) */
    #[Assert\Uuid]
    private ?UserProfileUid $profile = null;

    /** Признак, что честными знаками может делиться с другими */
    private bool $share = false;

    /** Грузовая таможенная декларация (номер) */
    private ?string $number = null;

    #[Assert\Valid]
    private ArrayCollection $files;

    public function __construct()
    {
        $this->files = new ArrayCollection();

        $ProductSignFileDTO = new ProductSignFileDTO();
        $this->addFiles($ProductSignFileDTO);
    }

    public function addFiles(ProductSignFileDTO $file): self
    {
        $this->files->add($file);
        return $this;
    }

    /**
     * Files
     */
    public function getFiles(): ArrayCollection
    {
        return $this->files;
    }

    public function setFiles(ArrayCollection $files): self
    {
        $this->files = $files;
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
     * Share
     */
    public function getShare(): bool
    {
        return $this->share;
    }

    public function setShare(bool $share): self
    {
        $this->share = $share;
        return $this;
    }

    public function isNew(): bool
    {
        return true;
    }

    public function isPurchase(): bool
    {
        return false;
    }

    public function getProduct(): null
    {
        return null;
    }

    public function getOffer(): null
    {
        return null;
    }

    public function getVariation(): null
    {
        return null;
    }

    public function getModification(): null
    {
        return null;
    }
}
