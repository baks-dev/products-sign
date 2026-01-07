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

namespace BaksDev\Products\Sign\UseCase\Admin\Status;

use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Orders\Order\Type\Items\Const\OrderProductItemConst;
use BaksDev\Products\Sign\Entity\Event\ProductSignEventInterface;
use BaksDev\Products\Sign\Type\Event\ProductSignEventUid;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusProcess;
use Symfony\Component\Validator\Constraints as Assert;

/** @see ProductSignEvent */
final readonly class ProductSignProcessDTO implements ProductSignEventInterface
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
    private ProductSignStatus $status;

    /**
     * Идентификатор заказа
     */
    #[Assert\NotBlank]
    private OrderUid $ord;

    /**
     * Константа единицы продукта
     */
    private ?OrderProductItemConst $product;

    #[Assert\Valid]
    private Invariable\ProductSignInvariableDTO $invariable;


    public function __construct(OrderUid $ord, ?OrderProductItemConst $product = null)
    {
        $this->ord = $ord;
        $this->product = $product;

        /** Статус Process «В резерве» */
        $this->status = new ProductSignStatus(ProductSignStatusProcess::class);
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
     * Статус
     */
    public function getStatus(): ProductSignStatus
    {
        return $this->status;
    }

    /**
     * Идентификатор заказа
     */
    public function getOrd(): OrderUid
    {
        return $this->ord;
    }


    public function getProduct(): ?OrderProductItemConst
    {
        return $this->product;
    }

    /**
     * Invariable
     */
    public function getInvariable(): Invariable\ProductSignInvariableDTO
    {
        return $this->invariable;
    }
}
