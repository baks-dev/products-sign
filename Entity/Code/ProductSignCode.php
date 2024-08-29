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

namespace BaksDev\Products\Sign\Entity\Code;

use BaksDev\Core\Entity\EntityEvent;
use BaksDev\Core\Entity\EntityReadonly;
use BaksDev\Core\Entity\EntityState;
use BaksDev\Files\Resources\Upload\UploadEntityInterface;
use BaksDev\Products\Sign\Entity\Event\ProductSignEvent;
use BaksDev\Products\Sign\Type\Id\ProductSignUid;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;

/* ProductSignCode */

#[ORM\Entity]
#[ORM\Table(name: 'product_sign_code')]
class ProductSignCode extends EntityReadonly implements UploadEntityInterface
{
    /** ID Product */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    #[ORM\Id]
    #[ORM\Column(type: ProductSignUid::TYPE)]
    private readonly ProductSignUid $main;

    /** ID события */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    #[ORM\OneToOne(targetEntity: ProductSignEvent::class, inversedBy: 'code')]
    #[ORM\JoinColumn(name: 'event', referencedColumnName: 'id')]
    private ProductSignEvent $event;

    /** Честный знак */
    #[ORM\Column(type: Types::STRING, unique: true)]
    private string $code;

    /** Название файла */
    #[Assert\NotBlank]
    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $name;

    /** Расширение файла */
    #[Assert\NotBlank]
    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $ext;

    /** Файл загружен на CDN */
    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $cdn = false;

    public function __construct(ProductSignEvent $event)
    {
        $this->event = $event;
        $this->main = $event->getMain();
    }

    public function __toString(): string
    {
        return (string) $this->event;
    }

    public function getId(): ProductSignUid
    {
        return $this->main;
    }

    public function getDto($dto): mixed
    {
        if($dto instanceof ProductSignCodeInterface)
        {
            return parent::getDto($dto);
        }

        throw new InvalidArgumentException(sprintf('Class %s interface error', $dto::class));
    }


    public function setEntity($dto): mixed
    {
        /* Если размер файла нулевой - не заполняем сущность */
        //        if(empty($dto->file) && empty($dto->getName()))
        //        {
        //            return false;
        //        }

        if($dto instanceof ProductSignCodeInterface || $dto instanceof self)
        {
            return parent::setEntity($dto);
        }

        throw new InvalidArgumentException(sprintf('Class %s interface error', $dto::class));
    }


    public function updFile(string $name, string $ext, int $size): void
    {
        $this->name = $name;
        $this->ext = $ext;
        $this->cdn = false;
    }

    public function updCdn(?string $ext = null): void
    {
        if($ext)
        {
            $this->ext = $ext;
            $this->cdn = true;
            return;
        }

        $this->cdn = false;
    }

    public function getExt(): string
    {
        return $this->ext;
    }

}
