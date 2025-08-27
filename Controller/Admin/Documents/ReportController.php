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

namespace BaksDev\Products\Sign\Controller\Admin\Documents;

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Products\Sign\Forms\ProductsSignReport\ProductSignReportDTO;
use BaksDev\Products\Sign\Forms\ProductsSignReport\ProductSignReportForm;
use BaksDev\Products\Sign\Repository\ProductSignReport\ProductSignReportInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsController]
#[RoleSecurity(['ROLE_ORDERS', 'ROLE_PRODUCT_SIGN'])]
final class ReportController extends AbstractController
{
    /**
     * Отчет о реализации честных знаков
     */
    #[Route('/admin/product/sign/report', name: 'admin.report', methods: ['GET', 'POST'])]
    public function off(
        Request $request,
        ProductSignReportInterface $ProductSignReport,
        TranslatorInterface $translator,
    ): Response
    {
        $ProductSignReportDTO = new ProductSignReportDTO()
            ->setSeller($this->getProfileUid());

        // Форма
        $form = $this
            ->createForm(
                ProductSignReportForm::class,
                $ProductSignReportDTO,
                ['action' => $this->generateUrl('products-sign:admin.report'),],
            )
            ->handleRequest($request);

        if($form->isSubmitted() && $form->isValid() && $form->has('product_sign_report'))
        {
            $this->refreshTokenForm($form);

            $ProductSignReport
                ->fromSeller($ProductSignReportDTO->getSeller())
                ->dateFrom($ProductSignReportDTO->getFrom())
                ->dateTo($ProductSignReportDTO->getTo())
                ->fromProductCategory($ProductSignReportDTO->getCategory()) // Определенной категории товары
                ->orderType($ProductSignReportDTO->getDelivery()) // только определенного типа доставки
            ;

            /** Получаем только в завершенные и возвращаем хвост кодировки */
            $data = $ProductSignReport
                ->onlyStatusDone()
                ->findAll();

            if(false === $data)
            {
                $this->addFlash(
                    'Отчет о реализации честных знаков',
                    'Отчета за указанный период не найдено',
                    'products-sign.admin',
                );

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


            $key = 1;

            foreach($data as $item)
            {
                if(false === $item->getProducts() || false === $item->getProducts()->valid())
                {
                    continue;
                }

                foreach($item->getProducts() as $product)
                {
                    $name = $product->getName();

                    if($product->getVariationValue())
                    {
                        $name = trim($name).' '.(
                            $product->getVariationReference() ?
                                $translator->trans(id: $product->getVariationValue(), domain: $product->getVariationReference()) :
                                $product->getVariationValue()
                            );
                    }

                    if($product->getModificationValue())
                    {
                        $name = trim($name).' '.(
                            $product->getModificationReference() ?
                                $translator->trans(id: $product->getModificationValue(), domain: $product->getModificationReference()) :
                                $product->getVariationValue()
                            );
                    }

                    if($product->getOfferValue())
                    {
                        $name = trim($name).' '.(
                            $product->getOfferReference() ?
                                $translator->trans(id: $product->getOfferValue(), domain: $product->getOfferReference()) :
                                $product->getOfferValue()
                            );
                    }

                    $sheet->setCellValue('A'.$key, trim($name)); // Наименование товара
                    $sheet->setCellValue('B'.$key, $product->getGtin()); // GTIN
                    $sheet->setCellValue('C'.$key, $product->codeSmallFormat()); // Код маркировки/агрегата

                    $key++;
                }
            }

            $response = new StreamedResponse(function() use ($writer) {
                $writer->save('php://output');
            }, Response::HTTP_OK);


            $filename =
                $ProductSignReportDTO->getSeller()?->getAttr().'('.
                $ProductSignReportDTO->getFrom()->format(('d.m.Y')).'-'.
                $ProductSignReportDTO->getTo()->format(('d.m.Y')).').xlsx';

            // Redirect output to a client’s web browser (Xls)
            $response->headers->set('Content-Type', 'application/vnd.ms-excel');
            $response->headers->set('Content-Disposition', 'attachment;filename="'.str_replace('"', '', $filename).'"');
            $response->headers->set('Cache-Control', 'max-age=0');


            return $response;
        }

        return $this->render(['form' => $form->createView()]);
    }
}
