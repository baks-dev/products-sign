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
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Sign\Repository\ProductSignByOrder\ProductSignByOrderInterface;
use BaksDev\Products\Sign\Repository\ProductSignByPart\ProductSignByPartInterface;
use BaksDev\Products\Sign\Type\Id\ProductSignUid;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[RoleSecurity('ROLE_ORDERS')]
final class CsvController extends AbstractController
{
    #[Route('/admin/order/document/sign/orders/{order}', name: 'admin.document.orders', methods: ['GET'])]
    public function orders(
        ProductSignByOrderInterface $productSignByOrder,
        #[ParamConverter(OrderUid::class)] $order,
    ): Response {

        $codes = $productSignByOrder
            ->forOrder($order)
            //->withStatusDone()
            ->execute();

        if($codes === false)
        {
            throw new InvalidArgumentException('Page not found');
        }

        $response = new StreamedResponse(function () use ($codes) {

            $handle = fopen('php://output', 'w+');

            // Запись заголовков
            //fputcsv($handle, ['Артикул', 'Наименование', 'Доступно', 'Место']);

            // Запись данных
            foreach($codes as $data)
            {
                /** Обрезаем честный знак до длины */

                // Позиция для третьей группы
                $thirdGroupPos = -1;

                preg_match_all('/\((\d{2})\)/', $data['code_string'], $matches, PREG_OFFSET_CAPTURE);

                if(count($matches[0]) >= 3)
                {
                    $thirdGroupPos = $matches[0][2][1];
                }

                // Если находимся на третьей группе, обрезаем строку
                if($thirdGroupPos !== -1)
                {
                    $markingcode = substr($data['code_string'], 0, $thirdGroupPos);
                    // Убираем круглые скобки
                    $data['code_string'] = str_replace(['(', ')'], '', $markingcode);
                }

                fputcsv($handle, [$data['code_string']], separator: ';', escape: ';');
            }

            fclose($handle);
        });

        $filename = uniqid('document_sign_', false).'.csv';
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');

        return $response;
    }

    #[Route('/admin/order/document/sign/parts/{part}', name: 'admin.document.parts', methods: ['GET'])]
    public function parts(
        ProductSignByPartInterface $productSignByPart,
        #[ParamConverter(ProductSignUid::class)] $part,
    ): Response {

        $codes = $productSignByPart
            ->forPart($part)
            ->execute();

        if($codes === false)
        {
            throw new InvalidArgumentException('Page not found');
        }

        //dd($codes);

        $response = new StreamedResponse(function () use ($codes) {

            $handle = fopen('php://output', 'w+');

            // Запись заголовков
            //fputcsv($handle, ['Артикул', 'Наименование', 'Доступно', 'Место']);

            // Запись данных
            foreach($codes as $data)
            {
                /** Обрезаем честный знак до длины */

                // Позиция для третьей группы
                $thirdGroupPos = -1;

                preg_match_all('/\((\d{2})\)/', $data['code_string'], $matches, PREG_OFFSET_CAPTURE);

                if(count($matches[0]) >= 3)
                {
                    $thirdGroupPos = $matches[0][2][1];
                }

                // Если находимся на третьей группе, обрезаем строку
                if($thirdGroupPos !== -1)
                {
                    $markingcode = substr($data['code_string'], 0, $thirdGroupPos);
                    // Убираем круглые скобки
                    $data['code_string'] = str_replace(['(', ')'], '', $markingcode);
                }

                // 100000000000000

                fputcsv($handle, [$data['code_string']], separator: ';', escape: ';');
            }

            fclose($handle);
        });

        $filename = uniqid('document_sign_', false).'.csv';
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');

        return $response;

    }

}