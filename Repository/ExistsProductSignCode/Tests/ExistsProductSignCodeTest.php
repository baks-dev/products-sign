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

namespace BaksDev\Products\Sign\Repository\ExistsProductSignCode\Tests;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Sign\Entity\Code\ProductSignCode;
use BaksDev\Products\Sign\Entity\Invariable\ProductSignInvariable;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Repository\ExistsProductSignCode\ExistsProductSignCodeInterface;
use BaksDev\Users\User\Type\Id\UserUid;
use PHPUnit\Framework\Attributes\DependsOnClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

#[When(env: 'test')]
#[Group('product-sign')]
class ExistsProductSignCodeTest extends KernelTestCase
{
    private static UserUid|false $usr = false;

    private static string|false $code = false;

    public static function setUpBeforeClass(): void
    {
        /** @var DBALQueryBuilder $DBALQueryBuilder */
        $DBALQueryBuilder = self::getContainer()->get(DBALQueryBuilder::class);

        $dbal = $DBALQueryBuilder->createQueryBuilder(self::class);

        $result = $dbal
            ->from(ProductSign::class, 'sign')
            ->addSelect('code.code')
            ->leftJoin(
                'sign',
                ProductSignCode::class,
                'code',
                'code.main = sign.id'
            )
            ->addSelect('invariable.usr')
            ->leftJoin(
                'sign',
                ProductSignInvariable::class,
                'invariable',
                'invariable.main = sign.id'
            )->fetchAssociative();


        self::$usr = isset($result['usr']) ? new UserUid($result['usr']) : false;
        self::$code = $result['code'] ?? false;

    }

    public function testUseCase(): void
    {
        if(self::$usr instanceof UserUid)
        {
            /** @var ExistsProductSignCodeInterface $ExistsProductSignCodeInterface */
            $ExistsProductSignCodeInterface = self::getContainer()->get(ExistsProductSignCodeInterface::class);
            $ExistsProductSignCodeEvent = $ExistsProductSignCodeInterface->isExists(
                self::$usr,
                self::$code
            );

            self::assertTrue($ExistsProductSignCodeEvent);
        }
        else
        {
            echo PHP_EOL."В базе отсутствует «Честный знак» : ".self::class.':'.__LINE__.PHP_EOL;
            self::assertTrue(true);
        }
    }
}
