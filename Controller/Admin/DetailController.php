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

use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Form\Search\SearchForm;
use BaksDev\Products\Product\Forms\ProductFilter\Admin\ProductFilterDTO;
use BaksDev\Products\Product\Forms\ProductFilter\Admin\ProductFilterForm;
use BaksDev\Products\Sign\Forms\ProductSignFilter\ProductSignFilterDTO;
use BaksDev\Products\Sign\Forms\ProductSignFilter\ProductSignFilterForm;
use BaksDev\Products\Sign\Repository\AllProductSign\AllProductSignInterface;
use BaksDev\Users\User\Type\Id\UserUid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use BaksDev\Core\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
#[RoleSecurity('ROLE_PRODUCT_SIGN')]
final class DetailController extends AbstractController
{
    #[Route('/admin/product/sign/detail/{part}', name: 'admin.detail', methods: ['GET', 'POST'])]
    public function detail(
        Request $request,
        AllProductSignInterface $allProductSign,
        int $part = 0,
    ): Response {

        // Поиск
        $search = new SearchDTO();
        $searchForm = $this->createForm(
            SearchForm::class,
            $search,
            ['action' => $this->generateUrl('products-sign:admin.index')]
        );
        $searchForm->handleRequest($request);


        /**
         * Фильтр продукции по ТП
         */
        $filter = new ProductFilterDTO($request);
        $filter->allVisible();

        $filterForm = $this->createForm(ProductFilterForm::class, $filter, [
            'action' => $this->generateUrl('products-sign:admin.index'),
        ]);
        $filterForm->handleRequest($request);

        /**
         * Фильтр статусам и даже
         */
        $filterSign = new ProductSignFilterDTO();
        $filterSignForm = $this->createForm(ProductSignFilterForm::class, $filterSign, [
            'action' => $this->generateUrl('products-sign:admin.index'),
        ]);
        $filterSignForm->handleRequest($request);


        // Получаем список
        $ProductSign = $allProductSign
            ->search($search)
            ->filter($filter)
            ->status($filterSign)
            ->findPaginator();


        return $this->render(
            [
                'query' => $ProductSign,
                'search' => $searchForm->createView(),
                'filter' => $filterForm->createView(),
                'status' => $filterSignForm->createView(),
            ]
        );
    }
}
