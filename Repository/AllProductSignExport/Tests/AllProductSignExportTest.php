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

namespace BaksDev\Products\Sign\Repository\AllProductSignExport\Tests;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Sign\Repository\AllProductSignExport\AllProductSignExportInterface;
use BaksDev\Products\Sign\Repository\AllProductSignExport\ProductSignExportResult;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DependsOnClass;
use PHPUnit\Framework\Attributes\Group;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

#[Group('products-sign')]
#[When(env: 'test')]
class AllProductSignExportTest extends KernelTestCase
{
    public function testUseCase(): void
    {
        self::assertTrue(true);

        /** @var AllProductSignExportInterface $AllProductSignExportRepository */
        $AllProductSignExportRepository = self::getContainer()->get(AllProductSignExportInterface::class);

        $results = $AllProductSignExportRepository
            ->forProfile(new UserProfileUid(UserProfileUid::TEST))
            ->dateFrom(new DateTimeImmutable())
            ->dateTo(new DateTimeImmutable())
            ->onlyTransfer()
            ->findAll();

        if(false === $results || false === $results->valid())
        {
            return;
        }

        foreach($results as $ProductSignExportResult)
        {
            // Вызываем все геттеры
            $reflectionClass = new ReflectionClass(ProductSignExportResult::class);
            $methods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);

            foreach($methods as $method)
            {
                // Методы без аргументов
                if($method->getNumberOfParameters() === 0)
                {
                    // Вызываем метод
                    $data = $method->invoke($ProductSignExportResult);
                    // dump($data);
                }
            }

        }

    }
}