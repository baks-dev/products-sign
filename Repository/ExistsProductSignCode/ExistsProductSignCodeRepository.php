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

namespace BaksDev\Products\Sign\Repository\ExistsProductSignCode;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Sign\Entity\Code\ProductSignCode;
use BaksDev\Products\Sign\Entity\Event\ProductSignEvent;
use BaksDev\Products\Sign\Entity\Invariable\ProductSignInvariable;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusError;
use BaksDev\Users\User\Type\Id\UserUid;

final class ExistsProductSignCodeRepository implements ExistsProductSignCodeInterface
{
    private DBALQueryBuilder $DBALQueryBuilder;

    public function __construct(
        DBALQueryBuilder $DBALQueryBuilder,
    ) {
        $this->DBALQueryBuilder = $DBALQueryBuilder;
    }

    /** Метод проверяет имеется ли у пользователя такой код (Без ошибки)  */
    public function isExists(UserUid $user, string $code): bool
    {
        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $dbal
            ->from(ProductSignCode::class, 'sign_code')
            ->where('sign_code.code = :code')
            ->setParameter('code', $code);

        $dbal
            ->join(
                'sign_code',
                ProductSign::class,
                'sign',
                'sign.id = sign_code.main'
            );

        $dbal
            ->join(
                'sign_code',
                ProductSignInvariable::class,
                'invariable',
                'invariable.main = sign_code.main AND invariable.usr = :usr'
            )
            ->setParameter(
                'usr',
                $user,
                UserUid::TYPE
            );


        $dbal
            ->join(
                'sign',
                ProductSignEvent::class,
                'event',
                'event.id = sign.event AND event.status != :status'
            )
            ->setParameter(
                'status',
                ProductSignStatusError::class,
                ProductSignStatus::TYPE
            );

        return $dbal->fetchExist();
    }
}
