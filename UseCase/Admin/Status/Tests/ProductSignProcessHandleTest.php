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

namespace BaksDev\Products\Sign\UseCase\Admin\Status\Tests;

use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Orders\Order\Type\Product\OrderProductUid;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Repository\CurrentEvent\ProductSignCurrentEventInterface;
use BaksDev\Products\Sign\Type\Id\ProductSignUid;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\Collection\ProductSignStatusCollection;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusProcess;
use BaksDev\Products\Sign\UseCase\Admin\New\Tests\ProductSignEditHandleTest;
use BaksDev\Products\Sign\UseCase\Admin\Status\ProductSignProcessDTO;
use BaksDev\Products\Sign\UseCase\Admin\Status\ProductSignStatusHandler;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * @group products-sign
 * @depends BaksDev\Products\Sign\UseCase\Admin\New\Tests\ProductSignEditHandleTest::class
 */
#[When(env: 'test')]
final class ProductSignProcessHandleTest extends KernelTestCase
{
    public function testUseCase(): void
    {
        /**
         * Инициируем статус для итератора тегов
         * @var ProductSignStatusCollection $status
         */
        $status = self::getContainer()->get(ProductSignStatusCollection::class);
        $status->cases();

        /** @var ProductSignCurrentEventInterface $ProductSignCurrentEvent */
        $ProductSignCurrentEvent = self::getContainer()->get(ProductSignCurrentEventInterface::class);
        $ProductSignEvent = $ProductSignCurrentEvent
            ->forProductSign(ProductSignUid::TEST)
            ->find();
        self::assertNotNull($ProductSignEvent);


        /** @see MaterialSignCancelDTO */

        $ProductSignDTO = new ProductSignProcessDTO($UserProfileUid = clone new UserProfileUid(), $OrderUid = new OrderUid());
        $ProductSignEvent->getDto($ProductSignDTO);
        self::assertSame($UserProfileUid, $ProductSignDTO->getProfile());
        self::assertSame($OrderUid, $ProductSignDTO->getOrd());
        self::assertTrue($ProductSignDTO->getStatus()->equals(ProductSignStatusProcess::class));


        /** @var ProductSignStatusHandler $ProductSignStatusHandler */
        $ProductSignStatusHandler = self::getContainer()->get(ProductSignStatusHandler::class);
        $handle = $ProductSignStatusHandler->handle($ProductSignDTO);

        self::assertTrue(($handle instanceof ProductSign), $handle.': Ошибка ProductSign');

    }
}