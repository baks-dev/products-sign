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

namespace BaksDev\Products\Sign\Controller\User;

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Core\Type\UidType\ParamConverter;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Sign\Repository\ProductSignByOrder\ProductSignByOrderInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[RoleSecurity('ROLE_USER')]
final class PrintController extends AbstractController
{
    #[Route('/product/sign/document/print/orders/{order}/{product}/{offer}/{variation}/{modification}', name: 'user.print.orders', methods: ['GET'])]
    public function orders(
        ProductSignByOrderInterface $productSignByOrder,
        #[ParamConverter(OrderUid::class)] $order,

        #[ParamConverter(ProductUid::class)] $product = null,
        #[ParamConverter(ProductOfferConst::class)] $offer = null,
        #[ParamConverter(ProductVariationConst::class)] $variation = null,
        #[ParamConverter(ProductModificationConst::class)] $modification = null,
    ): Response
    {

        if(false === is_null($product))
        {
            $productSignByOrder
                ->product($product)
                ->offer($offer)
                ->variation($variation)
                ->modification($modification);
        }

        $codes = $productSignByOrder
            ->profile($this->getCurrentProfileUid())
            ->withStatusDone()
            ->forOrder($order)
            ->findAll();


        return $this->render(
            ['codes' => $codes],
            routingName: 'user.print'
        );
    }

}
