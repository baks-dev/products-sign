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

namespace BaksDev\Products\Sign\Controller\Admin;

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Core\Type\UidType\ParamConverter;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Repository\ProductSignByPart\ProductSignByPartInterface;
use BaksDev\Products\Sign\UseCase\Admin\Delete\ProductSignDeleteDTO;
use BaksDev\Products\Sign\UseCase\Admin\Delete\ProductSignDeleteForm;
use BaksDev\Products\Sign\UseCase\Admin\Delete\ProductSignDeleteHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[RoleSecurity('ROLE_PRODUCT_SIGN_DELETE')]
final class DeleteController extends AbstractController
{
    #[Route('/admin/product/sign/delete/{part}', name: 'admin.delete', methods: ['GET', 'POST'])]
    public function delete(
        Request $request,
        string $part,
        ProductSignByPartInterface $ProductSignByPart,
        ProductSignDeleteHandler $ProductSignDeleteHandler,
    ): Response
    {

        $ProductSignDeleteDTO = new ProductSignDeleteDTO($this->getProfileUid());

        $form = $this
            ->createForm(
                type: ProductSignDeleteForm::class,
                data: $ProductSignDeleteDTO,
                options: [
                    'action' => $this->generateUrl('products-sign:admin.delete', ['part' => $part]),
                ])
            ->handleRequest($request);

        if($form->isSubmitted() && $form->isValid() && $form->has('product_sign_delete'))
        {

            /** Получаем все честные знаки по партии */

            $signs = $ProductSignByPart
                ->forPart($part)
                ->withStatusError()
                ->withStatusNew()
                ->findAll();

            if(false === $signs || false === $signs->valid())
            {
                $this->addFlash(
                    'page.cancel',
                    'danger.cancel',
                    'products-sign.admin',
                    'Группы не найдено'
                );

                return $this->redirectToRoute('products-sign:admin.index');
            }


            foreach($signs as $ProductSignByPartResult)
            {
                $ProductSignDeleteDTO = new ProductSignDeleteDTO($this->getProfileUid());
                $ProductSignDeleteDTO->setId($ProductSignByPartResult->getSignEvent());
                $handle = $ProductSignDeleteHandler->handle($ProductSignDeleteDTO);
            }

            $this->addFlash(
                'page.delete',
                $handle instanceof ProductSign ? 'success.delete' : 'danger.delete',
                'products-sign.admin',
                $handle
            );

            return $this->redirectToRoute('products-sign:admin.index');
        }

        return $this->render([
            'form' => $form->createView(),
            //'name' => $ProductSignEvent->getNameByLocale($this->getLocale()), // название согласно локали
        ]);
    }
}
