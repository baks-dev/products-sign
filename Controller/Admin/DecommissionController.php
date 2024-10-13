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

namespace BaksDev\Products\Sign\Controller\Admin;

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Core\Type\UidType\ParamConverter;
use BaksDev\Delivery\Entity\Delivery;
use BaksDev\Delivery\Entity\Event\DeliveryEvent;
use BaksDev\Delivery\UseCase\Admin\NewEdit\DeliveryDTO;
use BaksDev\Delivery\UseCase\Admin\NewEdit\DeliveryForm;
use BaksDev\Delivery\UseCase\Admin\NewEdit\DeliveryHandler;
use BaksDev\Products\Category\Type\Id\CategoryProductUid;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Sign\Entity\Event\ProductSignEvent;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Type\Id\ProductSignUid;
use BaksDev\Products\Sign\UseCase\Admin\NewEdit\ProductSignDTO;
use BaksDev\Products\Sign\UseCase\Admin\NewEdit\ProductSignForm;
use BaksDev\Products\Sign\UseCase\Admin\NewEdit\ProductSignHandler;
use BaksDev\Products\Sign\UseCase\Admin\Decommission\DecommissionProductSignDTO;
use BaksDev\Products\Sign\UseCase\Admin\Decommission\DecommissionProductSignForm;
use BaksDev\Products\Sign\UseCase\Admin\Decommission\DecommissionProductSignHandler;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
#[RoleSecurity('ROLE_PRODUCT_SIGN_OFF')]
final class DecommissionController extends AbstractController
{
    #[Route('/admin/product/sign/decommission/{category}/{product}/{offer}/{variation}/{modification}', name: 'admin.decommission', methods: ['GET', 'POST'])]
    public function off(
        Request $request,
        DecommissionProductSignHandler $OffProductSignHandler,
        #[ParamConverter(CategoryProductUid::class)] $category = null,
        #[ParamConverter(ProductUid::class)] $product = null,
        #[ParamConverter(ProductOfferConst::class)] $offer = null,
        #[ParamConverter(ProductVariationConst::class)] $variation = null,
        #[ParamConverter(ProductModificationConst::class)] $modification = null
    ): Response {

        $OffProductSignDTO = new DecommissionProductSignDTO();
        $OffProductSignDTO->setCategory($category);
        $OffProductSignDTO->setProduct($product);
        $OffProductSignDTO->setOffer($offer);
        $OffProductSignDTO->setVariation($variation);
        $OffProductSignDTO->setModification($modification);

        // Форма
        $form = $this->createForm(DecommissionProductSignForm::class, $OffProductSignDTO, [
            'action' => $this->generateUrl('products-sign:admin.decommission'),
        ]);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid() && $form->has('product_sign_off'))
        {
            $this->refreshTokenForm($form);

            $handle = $OffProductSignHandler->handle($OffProductSignDTO);

            $this->addFlash(
                'page.off',
                $handle instanceof ProductSignUid ? 'success.off' : $handle,
                'products-sign.admin',
                $handle
            );

            return $this->redirectToRoute('products-sign:admin.index');
        }

        return $this->render(['form' => $form->createView()]);
    }
}
