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

use BaksDev\Core\Entity\AbstractHandler;
use BaksDev\Products\Sign\Entity\Event\ProductSignEvent;
use BaksDev\Products\Sign\Entity\Event\ProductSignEventInterface;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Messenger\ProductSignMessage;
use BaksDev\Products\Sign\Type\Event\ProductSignEventUid;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusNew;
use Doctrine\Common\Collections\Criteria;

final class ProductSignStatusHandler extends AbstractHandler
{
    public function handle(ProductSignEventInterface $command): string|ProductSign
    {
        $this
            ->setCommand($command)
            ->preEventPersistOrUpdate(ProductSign::class, ProductSignEvent::class);

        /** Валидация всех объектов */
        if($this->validatorCollection->isInvalid())
        {
            return $this->validatorCollection->getErrorUniqid();
        }

        $this->flush();

        /** Если отмена честных знаков  */
        if(true === ($command->getEvent() instanceof ProductSignEventUid) && $command->getStatus()->equals(ProductSignStatusNew::class))
        {
            /** Получаем предыдущее событие для удаления */
            $lastProductSignEvent = $this
                ->getRepository(ProductSignEvent::class)
                ->find($command->getEvent());

            if($lastProductSignEvent instanceof ProductSignEvent)
            {
                $lastProductSignEvent->cancel();
                $this->flush();
            }
        }

        /* Отправляем сообщение в шину */
        $this->messageDispatch->dispatch(
            message: new ProductSignMessage($this->main->getId(), $this->main->getEvent(), $command->getEvent()),
            transport: 'products-sign'
        );

        return $this->main;
    }
}
