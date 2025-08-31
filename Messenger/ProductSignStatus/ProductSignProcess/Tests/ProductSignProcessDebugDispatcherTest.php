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

namespace BaksDev\Products\Sign\Messenger\ProductSignStatus\ProductSignProcess\Tests;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Sign\Messenger\ProductSignStatus\ProductSignProcess\ProductSignProcessMessage;
use BaksDev\Products\Sign\Type\Id\ProductSignUid;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Type\Id\UserUid;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DependsOnClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

#[When(env: 'test')]
#[Group('product-sign')]
class ProductSignProcessDebugDispatcherTest extends KernelTestCase
{
    public function testUseCase(): void
    {
        /** @var MessageDispatchInterface $MessageDispatchInterface */
        $MessageDispatchInterface = self::getContainer()->get(MessageDispatchInterface::class);

        $ProductSignProcessMessage = new ProductSignProcessMessage(
            order: new OrderUid(''),
            part: new ProductSignUid('01983496-b039-728c-8613-7ea7e594f9bf'),
            user: new UserUid('01878127-9b8c-7f33-8380-5f83bb10e9ad'),
            profile: new UserProfileUid('018e9e8f-9a83-7af7-a904-f34b393d69bf'),
            product: new ProductUid('018f7c64-c247-72c0-80ad-77aecb29cfbf'),
            offer: new ProductOfferConst('018f7c64-c210-702d-9078-aacf56260948'),
            variation: new ProductVariationConst('018f7c64-c210-702d-9078-aacf5623a7ab'),
            modification: new ProductModificationConst('018f7c64-c210-702d-9078-aacf5610effe'),
        );

        $MessageDispatchInterface->dispatch($ProductSignProcessMessage);
    }
}