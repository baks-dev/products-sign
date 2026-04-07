<?php
/*
 *  Copyright 2026.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Products\Sign\Controller\Admin\Documents;

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Form\Search\SearchForm;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Core\Twig\CallTwigFuncExtension;
use BaksDev\Products\Product\Forms\ProductFilter\Admin\ProductFilterDTO;
use BaksDev\Products\Product\Forms\ProductFilter\Admin\ProductFilterForm;
use BaksDev\Products\Sign\Forms\ProductSignFilter\ProductSignFilterDTO;
use BaksDev\Products\Sign\Forms\ProductSignFilter\ProductSignFilterForm;
use BaksDev\Products\Sign\Repository\GroupProductSigns\GroupProductSignsInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

#[AsController]
#[RoleSecurity('ROLE_PRODUCT_SIGN')]
final class IndexPrintController extends AbstractController
{
    #[Route('/admin/product/signs/index/print/{page<\d+>}', name: 'admin.index.print', methods: ['GET'])]
    public function index(
        Request $request,
        GroupProductSignsInterface $groupProductSigns,
        TranslatorInterface $translator,
        Environment $environment,
        int $page = 0,
    ): Response
    {
        // Поиск
        $search = new SearchDTO();

        $searchForm = $this
            ->createForm(
                type: SearchForm::class,
                data: $search,
                options: ['action' => $this->generateUrl('products-sign:admin.index')],
            )
            ->handleRequest($request);


        /**
         * Фильтр продукции по ТП
         */
        $filter = new ProductFilterDTO()
            ->hiddenMaterials()
            ->allVisible();

        $filterForm = $this
            ->createForm(
                type: ProductFilterForm::class,
                data: $filter,
                options: ['action' => $this->generateUrl('products-sign:admin.index'),],
            )
            ->handleRequest($request);

        /**
         * Фильтр статусам и даже
         */
        $filterSign = new ProductSignFilterDTO();

        $filterSignForm = $this
            ->createForm(
                type: ProductSignFilterForm::class,
                data: $filterSign,
                options: ['action' => $this->generateUrl('products-sign:admin.index'),],
            )
            ->handleRequest($request);


        // Получаем список
        $ProductSign = $groupProductSigns
            ->search($search)
            ->filter($filter)
            ->status($filterSign)
            ->findPaginator();

        if(empty($ProductSign->getData()))
        {
            return $this->redirectToReferer();
        }


        // Создаем новый объект Spreadsheet
        $spreadsheet = new Spreadsheet();
        $writer = new Xlsx($spreadsheet);

        // Получаем текущий активный лист
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setAutoSize(true);
        $sheet->getColumnDimension('C')->setAutoSize(true);
        $sheet->getColumnDimension('D')->setAutoSize(true);
        $sheet->getColumnDimension('E')->setAutoSize(true);
        $sheet->getColumnDimension('F')->setAutoSize(true);
        $sheet->getColumnDimension('G')->setAutoSize(true);
        $sheet->getColumnDimension('H')->setAutoSize(true);

        $key = 1;

        $call = $environment->getExtension(CallTwigFuncExtension::class);

        foreach($ProductSign->getData() as $item)
        {

            $strOffer = '';

            /* Множественный вариант торгового предложения */
            $variation = $call->call(
                $environment,
                $item['product_variation_value'],
                $item['product_variation_reference'].'_render',
            );

            $strOffer .= $variation ? ' '.trim($variation) : null;


            /* Модификация множественного варианта торгового предложения */
            $modification = $call->call(
                $environment,
                $item['product_modification_value'],
                $item['product_modification_reference'].'_render',
            );

            $strOffer .= $modification ? ' '.trim($modification) : null;

            /**
             * Торговое предложение
             */

            $offer = $call->call(
                $environment,
                $item['product_offer_value'],
                $item['product_offer_reference'].'_render',
            );


            $strOffer .= $offer ? ' '.trim($offer) : null;

            $strOffer .= $item['product_offer_postfix'] ? ' '.$item['product_offer_postfix'] : '';
            $strOffer .= $item['product_variation_postfix'] ? ' '.$item['product_variation_postfix'] : '';
            $strOffer .= $item['product_modification_postfix'] ? ' '.$item['product_modification_postfix'] : '';

            $sheet->setCellValue('A'.$key, $item['mod_date']); // Дата
            $sheet->setCellValue('B'.$key, $translator->trans(id: $item['sign_status'], domain: 'products-sign.status')); // Количество
            $sheet->setCellValue('C'.$key, $item['counter']); // Количество
            $sheet->setCellValue('D'.$key, $item['product_name']); // Наименование товара
            $sheet->setCellValue('E'.$key, str_replace(' /', '/', $strOffer)); // Наименование товара
            $sheet->setCellValue('F'.$key, $item['product_article']); // Наименование товара

            $array = json_decode($item['sign_number'], true, 512, JSON_THROW_ON_ERROR);
            $sheet->setCellValue('G'.$key, implode(',', array_column($array, 'number'))); // ГТД

            $sheet->setCellValue('H'.$key, $item['order_number']); // заказ


            $key++;

        }

        $response = new StreamedResponse(function() use ($writer) {
            $writer->save('php://output');
        }, Response::HTTP_OK);


        $filename = time().'.xlsx';

        // Redirect output to a client’s web browser (Xls)
        $response->headers->set('Content-Type', 'application/vnd.ms-excel');
        $response->headers->set('Content-Disposition', 'attachment;filename="'.str_replace('"', '', $filename).'"');
        $response->headers->set('Cache-Control', 'max-age=0');


        return $response;


    }
}
