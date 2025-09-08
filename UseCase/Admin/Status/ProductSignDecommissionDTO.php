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

namespace BaksDev\Products\Sign\UseCase\Admin\Status;

use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Sign\Entity\Event\ProductSignEventInterface;
use BaksDev\Products\Sign\Type\Event\ProductSignEventUid;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusDecommission;
use BaksDev\Products\Sign\UseCase\Admin\Status\Invariable\ProductSignInvariableDTO;
use Symfony\Component\Validator\Constraints as Assert;

/** @see MaterialSignEvent */
final class ProductSignDecommissionDTO implements ProductSignEventInterface
{
    /**
     * Идентификатор события
     */
    #[Assert\Uuid]
    #[Assert\NotBlank]
    private ProductSignEventUid $id;

    /**
     * Статус
     */
    #[Assert\NotBlank]
    private readonly ProductSignStatus $status;

    #[Assert\Valid]
    private ProductSignInvariableDTO $invariable;

    private ?OrderUid $ord = null;

    public function __construct()
    {
        /** Статус Off «Списание» */
        $this->status = new ProductSignStatus(ProductSignStatusDecommission::class);
        $this->invariable = new ProductSignInvariableDTO();
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
     * Статус
     */
    public function getStatus(): ProductSignStatus
    {
        return $this->status;
    }

    /**
     * Invariable
     */
    public function getInvariable(): ProductSignInvariableDTO
    {
        return $this->invariable;
    }

    public function getOrd(): ?OrderUid
    {
        return $this->ord;
    }

    public function setOrd(?OrderUid $ord): self
    {
        $this->ord = $ord;
        return $this;
    }
}
