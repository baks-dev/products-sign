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

namespace BaksDev\Products\Sign\UseCase\Admin\New;

use BaksDev\Products\Sign\Entity\Event\ProductSignEventInterface;
use BaksDev\Products\Sign\Type\Event\ProductSignEventUid;
use BaksDev\Products\Sign\Type\Id\ProductSignUid;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\Collection\ProductSignStatusInterface;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusError;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusNew;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Symfony\Component\Validator\Constraints as Assert;

/** @see MaterialSignEvent */
final class ProductSignDTO implements ProductSignEventInterface
{
    /**
     * Идентификатор события
     */
    #[Assert\Uuid]
    private ?ProductSignEventUid $id = null;

    /**
     * Код честного знака
     */
    #[Assert\Valid]
    private Code\ProductSignCodeDTO $code;


    /**
     * Статус
     */
    #[Assert\NotBlank]
    private ProductSignStatus $status;


    /**
     * Профиль пользователя
     *
     * @deprecated переносится в ProductSignInvariable
     */
    #[Assert\Uuid]
    private ?UserProfileUid $profile = null;


    /**
     * Код честного знака
     */
    #[Assert\Valid]
    private Invariable\ProductSignInvariableDTO $invariable;


    public function __construct()
    {
        $this->status = new ProductSignStatus(ProductSignStatusNew::class);
        $this->code = new Code\ProductSignCodeDTO();
        $this->invariable = new Invariable\ProductSignInvariableDTO();
    }

    /**
     * Идентификатор события
     */
    public function getEvent(): ?ProductSignEventUid
    {
        return $this->id;
    }

    public function setId(ProductSignEventUid $id): void
    {
        $this->id = $id;
    }

    /**
     * Status
     */
    public function getStatus(): ProductSignStatus
    {
        if($this->status->equals(ProductSignStatusError::class))
        {
            $this->invariable->setPart(new ProductSignUid());
        }

        return $this->status;
    }

    public function setStatus(ProductSignStatus|ProductSignStatusInterface|string $status): self
    {
        if(!$status instanceof ProductSignStatus)
        {
            $status = new ProductSignStatus($status);
        }

        $this->status = $status;

        return $this;
    }

    /**
     * Code
     */
    public function getCode(): Code\ProductSignCodeDTO
    {
        return $this->code;
    }

    public function setCode(Code\ProductSignCodeDTO $code): self
    {
        $this->code = $code;
        return $this;
    }

    /**
     * Invariable
     */
    public function getInvariable(): Invariable\ProductSignInvariableDTO
    {
        return $this->invariable;
    }

    public function setInvariable(Invariable\ProductSignInvariableDTO $invariable): self
    {
        $this->invariable = $invariable;
        return $this;
    }


    /**
     * Profile
     *
     * @deprecated переносится в ProductSignInvariable
     */
    public function setProfile(?UserProfileUid $profile): self
    {
        $this->profile = $profile;
        return $this;
    }

    /**  @deprecated переносится в ProductSignInvariable */
    public function getProfile(): ?UserProfileUid
    {
        return $this->profile;
    }
}
