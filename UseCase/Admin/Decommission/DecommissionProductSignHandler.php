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

namespace BaksDev\Products\Sign\UseCase\Admin\Decommission;

use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Repository\ProductSignNew\ProductSignNewInterface;
use BaksDev\Products\Sign\Type\Id\ProductSignUid;
use BaksDev\Products\Sign\UseCase\Admin\Status\ProductSignDecommissionDTO;
use BaksDev\Products\Sign\UseCase\Admin\Status\ProductSignStatusHandler;

final class DecommissionProductSignHandler
{
    public function __construct(
        private readonly ProductSignNewInterface $productSignNew,
        private readonly ProductSignStatusHandler $productSignStatusHandler,
    ) {}

    /** @see ProductSign */
    public function handle(DecommissionProductSignDTO $command): string|ProductSignUid
    {
        $ProductSignUid = new ProductSignUid();

        /** Получаем свободный честный знак для списания */
        for($i = 1; $i <= $command->getTotal(); $i++)
        {
            $ProductSignEvent = $this->productSignNew
                ->forUser($command->getUsr())
                ->forProfile($command->getProfile())
                ->forProduct($command->getProduct())
                ->forOfferConst($command->getOffer())
                ->forVariationConst($command->getVariation())
                ->forModificationConst($command->getModification())
                ->getOneProductSign();

            if($ProductSignEvent === false)
            {
                return 'Недостаточное количество честных знаков';
            }

            /** Меняем статус и присваиваем идентификатор партии  */
            $ProductSignOffDTO = new ProductSignDecommissionDTO($command->getProfile());
            $ProductSignInvariableDTO = $ProductSignOffDTO->getInvariable();
            $ProductSignInvariableDTO->setPart($ProductSignUid);

            $ProductSignEvent->getDto($ProductSignOffDTO);

            $handle = $this->productSignStatusHandler->handle($ProductSignOffDTO);

            if(false === ($handle instanceof ProductSign))
            {
                return sprintf('%s: Ошибка при списании честных знаков', $handle);
            }
        }

        return $ProductSignUid;
    }
}
