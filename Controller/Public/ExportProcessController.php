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
final class ExportProcessController extends AbstractController
{
    /**
     * Отчет о «Честных знаках» на передачу честных знаков по свойству:
     * profile == {profile}
     * profile != seller
     * status = process
     */
    #[Route('/product/signs/export/process/{profile}', name: 'public.export.process', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        #[MapEntity] UserProfile $profile,
        AllProductSignExportInterface $allProductSign,
        int $page = 0,
    ): Response
    {

        // /product/signs/export/done/018d3075-6e7b-7b5e-95f6-923243b1fa3d?from=2024-09-01T00:00:00&to=2024-09-31T00:00:00

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
            ->execute();

        if($data === false)
        {
            return new JsonResponse([
                'status' => 404,
                'message' => 'За указанный период выполненных честных знаков не найдено'
            ], status: 404);
        }

        $rows = null;

        foreach($data as $key => $item)
        {
            /** Номер заказа */
            $rows[$key]['number'] = $item['number'];

            /** Дата доставки в формате ISO8601 */
            $datetime = new DateTimeImmutable($item['delivery_date']);
            $rows[$key]['date'] = $datetime->format(DateTimeInterface::ATOM);

            /** Полная стоимость заказа */
            $total = new Money($item['order_total'], true);
            $rows[$key]['total'] = $total->getValue();
            $rows[$key]['delivery'] = $item['delivery_name'];

            /** Перечисляем все заказы и их честный знак */
            $products = json_decode($item['products'], true, 512, JSON_THROW_ON_ERROR);

            $products = array_map(static function($item) {
                $price = new Money($item['price'], true);
                $item['price'] = $price->getValue();
                return $item;
            }, $products);

            $rows[$key]['products'] = $products;
        }

        return new JsonResponse($rows);
    }
}
