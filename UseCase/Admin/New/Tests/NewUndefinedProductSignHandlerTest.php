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
 *
 */

declare(strict_types=1);

namespace BaksDev\Products\Sign\UseCase\Admin\New\Tests;

use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Sign\Entity\Event\ProductSignEvent;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Type\Id\ProductSignUid;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\Collection\ProductSignStatusCollection;
use BaksDev\Products\Sign\UseCase\Admin\New\ProductSignHandler;
use BaksDev\Products\Supply\UseCase\Admin\ProductsSign\New\ProductSignNewDTO;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Type\Id\UserUid;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

#[Group('products-sign')]
#[When(env: 'test')]
class NewUndefinedProductSignHandlerTest extends KernelTestCase
{
    public static function setUpBeforeClass(): void
    {
        $container = self::getContainer();

        /**
         * Инициализируем статусы
         *
         * @var ProductSignStatusCollection $ProductSignStatusCollection
         */
        $ProductSignStatusCollection = self::getContainer()->get(ProductSignStatusCollection::class);
        $ProductSignStatusCollection->cases();

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $ProductSign = $em->getRepository(ProductSign::class)
            ->find(ProductSignUid::TEST);

        if($ProductSign)
        {
            $em->remove($ProductSign);
        }

        $ProductSignEvent = $em->getRepository(ProductSignEvent::class)
            ->findBy(['main' => ProductSignUid::TEST]);

        foreach($ProductSignEvent as $remove)
        {
            $em->remove($remove);
        }

        $em->flush();
    }

    public function testUseCase(): void
    {
        $ProductSignNewDTO = new ProductSignNewDTO();

        /** Product */
        $ProductSignNewDTO->getInvariable()
            ->setProduct(new ProductUid)
            ->setOffer(new ProductOfferConst)
            ->setVariation(new ProductVariationConst)
            ->setModification(new ProductModificationConst);

        /** Code */
        $ProductSignNewDTO->getCode()
            ->setCode('(01)04600000000000(21)test_code')
            ->setName('test_name')
            ->setPngExt();

        /** Invariable */
        $ProductSignNewDTO->getInvariable()
            ->setPart('test_part')
            ->setUsr(new UserUid)
            ->setProfile(new UserProfileUid());

        self::bootKernel();

        /** @var ProductSignHandler $ProductSignHandler */
        $ProductSignHandler = self::getContainer()->get(ProductSignHandler::class);
        $handle = $ProductSignHandler->handle($ProductSignNewDTO);

        self::assertTrue(($handle instanceof ProductSign), $handle.': Ошибка ProductSign');
    }
}
