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

namespace BaksDev\Products\Sign\Repository\ProductSignNew\Tests;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Sign\Entity\Event\ProductSignEvent;
use BaksDev\Products\Sign\Entity\Invariable\ProductSignInvariable;
use BaksDev\Products\Sign\Repository\ProductSignNew\ProductSignNewInterface;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusNew;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @group product-sign
 */
#[When(env: 'test')]
class ProductSignNewTest extends KernelTestCase
{
    private static string|false $user = false;
    private static string|false $profile = false;
    private static string|false $product = false;
    private static ?string $offer = null;
    private static ?string $variation = null;
    private static ?string $modification = null;


    public static function setUpBeforeClass(): void
    {
        // Бросаем событие консольной комманды
        $dispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        $event = new ConsoleCommandEvent(new Command(), new StringInput(''), new NullOutput());
        $dispatcher->dispatch($event, 'console.command');

        /** @var DBALQueryBuilder $DBALQueryBuilder */
        $DBALQueryBuilder = self::getContainer()->get(DBALQueryBuilder::class);

        $dbal = $DBALQueryBuilder->createQueryBuilder(self::class);

        $result = $dbal
            ->select('*')
            ->from(ProductSignInvariable::class, 'invariable')
            ->leftJoin(
                'invariable',
                ProductSignEvent::class,
                'event',
                'event.id = invariable.event AND status = :status AND profile IS NOT NULL'
            )
            ->setParameter('status', ProductSignStatusNew::class, ProductSignStatus::TYPE)
            ->setMaxResults(1)
            ->fetchAssociative();


        self::$user = $result['usr'] ?? false;
        self::$profile = $result['profile'] ?? false;
        self::$product = $result['product'] ?? false;
        self::$offer = $result['offer'] ?? null;
        self::$variation = $result['variation'] ?? null;
        self::$modification = $result['modification'] ?? null;

    }

    public function testUseCase(): void
    {
        self::assertTrue(true);
        return;

        /** @var ProductSignNewInterface $ProductSignNewInterface */
        $ProductSignNewInterface = self::getContainer()->get(ProductSignNewInterface::class);

        // self::$user = "0191d360-1613-7d30-8859-f43991ffe926";
        // self::$profile = "0191d362-a007-7d4b-863e-bdd8a5d9a28a";
        // self::$product = "01876b4b-886d-7cff-a70e-b73559356089";
        // self::$offer = "01878a7a-aa04-7c07-ab5c-426ad8b01ae0";
        // self::$variation = "01878a7a-aa00-77a6-9f90-4dc47498a632";
        // self::$modification = "01878a7a-a9ff-7e4f-8809-151e88674d80";

        $ProductSignEvent = $ProductSignNewInterface
            ->forUser(self::$user)
            ->forProfile(self::$profile)
            ->forProduct(self::$product)
            ->forOfferConst(self::$offer)
            ->forVariationConst(self::$variation)
            ->forModificationConst(self::$modification)
            ->getOneProductSign();

        // dump($ProductSignEvent);

        self::assertNotFalse($ProductSignEvent);

    }


}
