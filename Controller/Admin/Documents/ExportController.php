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
use BaksDev\Products\Sign\Repository\ExportProductSignByStatus\ExportProductSignByStatusInterface;
use DateTimeImmutable;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
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
final class ExportController extends AbstractController
{
    /**
     * Отчет о реализации честных знаков
     */
    #[Route('/admin/product/sign/export/{status}', name: 'admin.export', methods: ['GET', 'POST'])]
    public function off(
        Request $request,
        ExportProductSignByStatusInterface $ExportProductSignByStatusRepository,
        TranslatorInterface $translator,
        string $status,
    ): Response
    {
        /** Получаем всю маркировку со статусом */
        $data = $ExportProductSignByStatusRepository
            ->status($status)
            ->findAll();

        if(false === $data || false === $data->valid())
        {
            $this->addFlash(
                'Честный знак',
                'Отчета о честных знаков c указанным статусом не найдено',
                'products-sign.admin',
            );

            return $this->redirectToReferer();
        }

        $filename = $status.'-'.(new DateTimeImmutable())->format(('d.m.Y')).').xlsx';

        $writer = new Writer();
        $writer->openToBrowser($filename);


        foreach($data as $key => $item)
        {
            $cells = [
                Cell::fromValue($item->getStatus()), // статус
                Cell::fromValue($item->getCode()), // Код маркировки
                Cell::fromValue($item->getProduct()), // Название продукта
            ];

            $row = new Row($cells);
            $writer->addRow($row);

        }

        $writer->close();
        exit;
    }
}
