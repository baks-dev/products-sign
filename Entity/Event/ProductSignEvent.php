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

namespace BaksDev\Products\Sign\Entity\Event;

use BaksDev\Core\Type\Locale\Locale;
use BaksDev\Core\Type\Modify\ModifyAction;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Orders\Order\Type\Product\OrderProductUid;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Sign\Entity\Code\ProductSignCode;
use BaksDev\Products\Sign\Entity\Modify\ProductSignModify;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Type\Event\ProductSignEventUid;
use BaksDev\Products\Sign\Type\Id\ProductSignUid;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use BaksDev\Core\Entity\EntityEvent;
use BaksDev\Core\Entity\EntityState;
use InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;


/* ProductSignEvent */

#[ORM\Entity]
#[ORM\Table(name: 'product_sign_event')]
#[ORM\Index(columns: ['status'])]
class ProductSignEvent extends EntityEvent
{
    public const TABLE = 'product_sign_event';

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
     * Профиль пользователя
     */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    #[ORM\Column(type: UserProfileUid::TYPE)]
    private UserProfileUid $profile;

    /**
     * ID продукции в заказе
     */
    #[ORM\Column(type: OrderUid::TYPE, nullable: true)]
    private ?OrderUid $ord = null;


    /** Комментарий */
    #[ORM\Column(type: Types::STRING, nullable: true)]
    private string $comment;

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
     * Status
     */
    public function getStatus(): ProductSignStatus
    {
        return $this->status;
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