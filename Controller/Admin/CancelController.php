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
use BaksDev\Products\Sign\Entity\Event\ProductSignEvent;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusProcess;
use BaksDev\Products\Sign\UseCase\Admin\Status\ProductSignCancelDTO;
use BaksDev\Products\Sign\UseCase\Admin\Status\ProductSignCancelForm;
use BaksDev\Products\Sign\UseCase\Admin\Status\ProductSignStatusHandler;
use InvalidArgumentException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[RoleSecurity('ROLE_PRODUCT_SIGN_STATUS')]
final class CancelController extends AbstractController
{
    #[Route('/admin/product/sign/cancel/{id}', name: 'admin.cancel', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        #[MapEntity] ProductSignEvent $ProductSignEvent,
        ProductSignStatusHandler $ProductSignStatusHandler,
    ): Response {

        if(!$ProductSignEvent->getStatus()->equals(ProductSignStatusProcess::class))
        {
            throw new InvalidArgumentException('Page Not Found');
        }

        $ProductSignStatusDTO = new ProductSignCancelDTO($this->getProfileUid());
        $ProductSignEvent->getDto($ProductSignStatusDTO);

        // Форма
        $form = $this->createForm(ProductSignCancelForm::class, $ProductSignStatusDTO, [
            'action' => $this->generateUrl('products-sign:admin.cancel', ['id' => $ProductSignStatusDTO->getEvent()]),
        ]);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid() && $form->has('product_sign_cancel'))
        {
            $this->refreshTokenForm($form);

            $handle = $ProductSignStatusHandler->handle($ProductSignStatusDTO);

            $this->addFlash(
                'page.cancel',
                $handle instanceof ProductSign ? 'success.cancel' : 'danger.cancel',
                'products-sign.admin',
                $handle
            );

            return $this->redirectToRoute('products-sign:admin.index');
        }

        return $this->render(['form' => $form->createView()]);
    }
}
