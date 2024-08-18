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
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\UseCase\Admin\Pdf\ProductSignPdfHandler;
use BaksDev\Products\Sign\UseCase\Admin\Pdf\ProductSignPdfDTO;
use BaksDev\Products\Sign\UseCase\Admin\Pdf\ProductSignPdfForm;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
#[RoleSecurity('ROLE_PRODUCT_SIGN_NEW')]
final class PdfController extends AbstractController
{
    #[Route('/admin/product/sign/pdf', name: 'admin.pdf', methods: ['GET', 'POST'])]
    public function news(
        Request $request,
        ProductSignPdfHandler $ProductSignHandler,
    ): Response
    {
        $ProductSignPdfDTO = new ProductSignPdfDTO($this->getUsr()?->getId(), $this->getProfileUid());


        // Форма
        $form = $this->createForm(ProductSignPdfForm::class, $ProductSignPdfDTO, [
            'action' => $this->generateUrl('products-sign:admin.pdf'),
        ]);

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid() && $form->has('product_sign_pdf'))
        {
            $this->refreshTokenForm($form);

            $handle = $ProductSignHandler->handle($ProductSignPdfDTO);

            $this->addFlash
            (
                'page.pdf',
                $handle === true ? 'success.pdf' : 'danger.pdf',
                'products-sign.admin'
            );

            return $this->redirectToReferer();
        }

        return $this->render(['form' => $form->createView()]);
    }
}