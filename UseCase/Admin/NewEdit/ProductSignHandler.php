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

namespace BaksDev\Products\Sign\UseCase\Admin\NewEdit;

use BaksDev\Core\Entity\AbstractHandler;
use BaksDev\Products\Sign\Entity\Event\ProductSignEvent;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Messenger\ProductSignMessage;
use DomainException;
use Imagick;


final class ProductSignHandler extends AbstractHandler
{

    public function handle(ProductSignDTO $command): string|ProductSign
    {

        /** Валидация DTO  */
        $this->validatorCollection->add($command);

        $this->main = new ProductSign();
        $this->event = new ProductSignEvent();

        try
        {
            $command->getEvent() ? $this->preUpdate($command, true) : $this->prePersist($command);
        }
        catch(DomainException $errorUniqid)
        {
            return $errorUniqid->getMessage();
        }

        /**
         * Присваиваем QR при загрузки файла
         */

        $ProductSignCodeDTO = $command->getCode();

        if($ProductSignCodeDTO->file !== null)
        {
            $Imagick = new Imagick();
            $Imagick->setResolution(200, 200);
            $Imagick->readImage($command->getCode()->file->getRealPath());
            $Imagick->setImageFormat('png');
            $imageString = $Imagick->getImageBlob();
            $base64Image = 'data:image/png;base64,'.base64_encode($imageString);
            $ProductSignCodeDTO->setQr($base64Image);
        }

        /** Валидация всех объектов */
        if($this->validatorCollection->isInvalid())
        {
            return $this->validatorCollection->getErrorUniqid();
        }

        $this->entityManager->flush();

        /* Отправляем сообщение в шину */
        $this->messageDispatch->dispatch(
            message: new ProductSignMessage($this->main->getId(), $this->main->getEvent(), $command->getEvent()),
            transport: 'products-sign'
        );

        return $this->main;
    }
}