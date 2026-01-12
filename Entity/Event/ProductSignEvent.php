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

namespace BaksDev\Products\Sign\Entity\Event;

use BaksDev\Core\Entity\EntityEvent;
use BaksDev\Orders\Order\Entity\Items\OrderProductItem;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Orders\Order\Type\Items\Const\OrderProductItemConst;
use BaksDev\Products\Sign\Entity\Code\ProductSignCode;
use BaksDev\Products\Sign\Entity\Event\Supply\ProductSignSupply;
use BaksDev\Products\Sign\Entity\Invariable\ProductSignInvariable;
use BaksDev\Products\Sign\Entity\Modify\ProductSignModify;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Type\Event\ProductSignEventUid;
use BaksDev\Products\Sign\Type\Id\ProductSignUid;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusNew;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusReturn;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;

/* ProductSignEvent */

#[ORM\Entity]
#[ORM\Table(name: 'product_sign_event')]
#[ORM\Index(columns: ['ord', 'status', 'product'])]
class ProductSignEvent extends EntityEvent
{
    /**
     * Идентификатор События
     */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    #[ORM\Id]
    #[ORM\Column(type: ProductSignEventUid::TYPE)]
    private ProductSignEventUid $id;

    /**
     * Идентификатор ProductSign
     */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    #[ORM\Column(type: ProductSignUid::TYPE)]
    private ?ProductSignUid $main = null;

    /**
     * Код честного знака
     */
    #[ORM\OneToOne(targetEntity: ProductSignCode::class, mappedBy: 'event', cascade: ['all'])]
    private ?ProductSignCode $code = null;

    /**
     * Постоянная величина
     */
    #[ORM\OneToOne(targetEntity: ProductSignInvariable::class, mappedBy: 'event', cascade: ['all'])]
    private ?ProductSignInvariable $invariable;

    /**
     * Модификатор
     */
    #[ORM\OneToOne(targetEntity: ProductSignModify::class, mappedBy: 'event', cascade: ['all'])]
    private ProductSignModify $modify;

    /**
     * Статус
     */
    #[ORM\Column(type: ProductSignStatus::TYPE)]
    private ProductSignStatus $status;

    /**
     * ID заказа
     */
    #[ORM\Column(type: OrderUid::TYPE, nullable: true)]
    private ?OrderUid $ord = null;

    /**
     * Константа единицы продукта
     */
    #[ORM\Column(type: OrderProductItemConst::TYPE, nullable: true)]
    private ?OrderProductItemConst $product = null;

    /** Идентификатор поставки */
    #[ORM\OneToOne(targetEntity: ProductSignSupply::class, mappedBy: 'event', cascade: ['all'])]
    private ?ProductSignSupply $supply = null;

    /** Комментарий */
    #[ORM\Column(type: Types::STRING, nullable: true)]
    private string $comment;

    /**
     * Профиль пользователя (null - общий)
     *
     * @deprecated переносится в ProductSignInvariable
     * @see ProductSignInvariable
     */
    #[ORM\Column(type: UserProfileUid::TYPE, nullable: true)]
    private ?UserProfileUid $profile = null;

    public function __construct()
    {
        $this->id = new ProductSignEventUid();
        $this->modify = new ProductSignModify($this);
    }

    /**
     * Идентификатор События
     */

    public function __clone()
    {
        $this->id = clone new ProductSignEventUid();
    }

    public function __toString(): string
    {
        return (string) $this->id;
    }

    public function getId(): ProductSignEventUid
    {
        return $this->id;
    }

    /** Присваиваем статус Return «Возврат» */
    public function return(): self
    {
        $this->status = new ProductSignStatus(ProductSignStatusReturn::class);
        return $this;
    }

    /** Присваиваем статус New «Новый» */
    public function cancel(): self
    {
        $this->status = new ProductSignStatus(ProductSignStatusNew::class);
        $this->ord = null; // удаляем связь с заказом
        $this->product = null; // удаляем связь с продуктом в заказе

        return $this;
    }

    /**
     * Идентификатор ProductSign
     */
    public function setMain(ProductSignUid|ProductSign|string $main): void
    {
        if(is_string($main))
        {
            $main = new ProductSignUid($main);
        }

        $this->main = $main instanceof ProductSign ? $main->getId() : $main;
    }


    public function getMain(): ?ProductSignUid
    {
        return $this->main;
    }

    /**
     * Profile
     */
    public function getProfile(): UserProfileUid
    {
        return $this->profile;
    }

    /**
     * Идентификатор владельца честного знака
     */
    public function getOwnerSignProfile(): UserProfileUid
    {
        return $this->invariable->getProfile();
    }

    /**
     * Status
     */
    public function getStatus(): ProductSignStatus
    {
        return $this->status;
    }

    public function deleteCode(): void
    {
        $this->code->deleteCode();
    }


    public function getDto($dto): mixed
    {
        if($dto instanceof ProductSignEventInterface)
        {
            return parent::getDto($dto);
        }

        throw new InvalidArgumentException(sprintf('Class %s interface error', $dto::class));
    }

    public function setEntity($dto): mixed
    {
        if($dto instanceof ProductSignEventInterface)
        {
            return parent::setEntity($dto);
        }

        throw new InvalidArgumentException(sprintf('Class %s interface error', $dto::class));
    }

}
