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

namespace BaksDev\Products\Sign\Controller\Public;

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Products\Sign\Repository\AllProductSignExport\AllProductSignExportInterface;
use BaksDev\Products\Sign\Repository\AllProductSignExport\ProductSignExportResult;
use BaksDev\Reference\Money\Type\Money;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final class ExportDoneController extends AbstractController
{
    /**
     * Отчет о «Честных знаках» на вывод из оборота:
     * seller = {profile}
     * status = done
     */
    #[Route('/product/signs/export/done/{profile}', name: 'public.export.done', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        #[MapEntity] UserProfile $profile,
        AllProductSignExportInterface $allProductSign,
    ): Response
    {

        $from = $request->get('from');

        if(empty($from))
        {
            throw new InvalidArgumentException('Параметр «from» должен быть допустимой датой.');
        }

        $from = new DateTimeImmutable($from);

        $to = $request->get('to');

        if(empty($to))
        {
            throw new InvalidArgumentException('Параметр «to» должен быть допустимой датой.');
        }

        $to = new DateTimeImmutable($to);

        $data = $allProductSign
            ->forProfile($profile)
            ->dateFrom($from)
            ->dateTo($to)
            ->onlyDoneBuyer() // Вывод из оборота (покупатель)
            ->findAll();

        if(false === $data || false === $data->valid())
        {
            return new JsonResponse([
                'status' => 404,
                'message' => 'За указанный период выполненных честных знаков не найдено',
            ], status: 404);
        }


        $rows = null;

        /** @var ProductSignExportResult $item */
        foreach($data as $key => $item)
        {
            /** Номер заказа */
            $rows[$key]['number'] = $item->getOrderNumber();

            /** Дата доставки в формате ISO8601 */
            $datetime = $item->getDeliveryDate();
            $rows[$key]['date'] = $datetime->format(DateTimeInterface::ATOM); // Updated ISO8601

            /** Полная стоимость заказа */
            $rows[$key]['documentamount'] = $item->getOrderTotalPrice()->getValue();

            $item->getInn() ? $rows[$key]['clientInn'] = $item->getInn() : null;
            $item->getKpp() ? $rows[$key]['clientKpp'] = $item->getKpp() : null;
            $item->getOkpo() ? $rows[$key]['clientOkpo'] = $item->getOkpo() : null;


            /** Перечисляем все заказы и их честный знак */

            $products = array_map(static function($item) {

                $chars = null;

                preg_match('/^(.*?)\(\d{2}\).{4}\(\d{2}\)/', $item->code, $matches);

                if(isset($matches[1]))
                {

                    // Преобразуем строку в массив символов
                    $chars = str_split($matches[1]);

                    // 1 символ (индекс 0)
                    if($chars[0] === '(')
                    {
                        unset($chars[0]);
                    }

                    // 4 символ (индекс 3)
                    if($chars[3] === ')')
                    {
                        unset($chars[3]);
                    }


                    // 19 символ (индекс 18)
                    if($chars[18] === '(')
                    {
                        unset($chars[18]);
                    }

                    // 22 символ (индекс 21)
                    if($chars[21] === ')')
                    {
                        unset($chars[21]);
                    }

                }

                return [
                    'good' => $item->article,
                    'count' => 1,
                    'price' => new Money($item->price, true)->getValue(),
                    'amount' => new Money($item->price, true)->getValue(),
                    'markingcode' => $chars ? implode('', $chars) : false,
                ];
            }, $item->getProducts());


            $rows[$key]['products'] = $products;
        }

        return new JsonResponse($rows);
    }
}
