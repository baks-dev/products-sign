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
use BaksDev\Orders\Order\Type\Product\OrderProductUid;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Sign\Entity\Event\ProductSignEventInterface;
use BaksDev\Products\Sign\Type\Event\ProductSignEventUid;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusProcess;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Symfony\Component\Validator\Constraints as Assert;

/** @see MaterialSignEvent */
final readonly class ProductSignProcessDTO implements ProductSignEventInterface
{
    /**
     * Идентификатор события
     */
    #[Assert\Uuid]
    #[Assert\NotBlank]
    private ProductSignEventUid $id;

    /**
     * Профиль пользователя
     *
     * @depricate
     */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private UserProfileUid $profile;

    /**
     * Статус
     */
    #[Assert\NotBlank]
    private ProductSignStatus $status;

    /**
     * Идентификатор заказа
     */
    #[Assert\NotBlank]
    private OrderUid $ord;

    #[Assert\Valid]
    private Invariable\ProductSignInvariableDTO $invariable;


    public function __construct(UserProfileUid $profile, OrderUid $ord)
    {
        $this->profile = $profile;
        $this->ord = $ord;

        /** Статус Process «В резерве» */
        $this->status = new ProductSignStatus(ProductSignStatusProcess::class);
        $this->invariable = new Invariable\ProductSignInvariableDTO();
        $this->invariable->setProfile($this->profile);

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
     * Профиль пользователя
     */
    public function getProfile(): UserProfileUid
    {
        return $this->profile;
    }

    /**
     * Идентификатор заказа
     */
    public function getOrd(): OrderUid
    {
        return $this->ord;
    }

    /**
     * Invariable
     */
    public function getInvariable(): Invariable\ProductSignInvariableDTO
    {
        return $this->invariable;
    }

}
