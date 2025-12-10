<?php
/*
 * Copyright 2025.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Products\Sign\Controller\Admin\Documents\Orders;

use BaksDev\Core\Twig\CallTwigFuncExtension;
use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Orders\Order\Repository\CurrentOrderEvent\CurrentOrderEventInterface;
use BaksDev\Products\Sign\Repository\GroupProductSignsByOrder\GroupProductSignsByOrderInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use BaksDev\Core\Controller\AbstractController;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

#[AsController]
final class LinksListController extends AbstractController
{
    #[Route('/admin/product/sign/order/links/{order}', name: 'admin.links.list', methods: ['GET', 'POST'])]
    public function index(
        #[MapEntity] Order $order,
        #[Autowire(env: 'HOST')] string $host,
        Request $request,
        GroupProductSignsByOrderInterface $GroupProductSignsByOrder,
        CurrentOrderEventInterface $CurrentOrderEventRepository,
        Environment $environment,
        UrlGeneratorInterface $UrlGenerator,
    ): Response
    {
        /** Получаем текущее событие заказа, чтобы получить его номер */
        $orderEvent = $CurrentOrderEventRepository
            ->forOrder($order)
            ->find();

        $productSigns = $GroupProductSignsByOrder
            ->forOrder($order)
            ->findAll();

        if(false === $productSigns || false === $productSigns->valid())
        {
            return $this->redirectToReferer();
        }

        $response = new StreamedResponse(function() use ($host, $UrlGenerator, $environment, $order, $productSigns) {
            $call = $environment->getExtension(CallTwigFuncExtension::class);

            $handle = fopen('php://output', 'wb+');

            /** Запись данных */
            foreach($productSigns as $productSign)
            {
                /** Торговое предложение */
                $offer = $call->call(
                    $environment,
                    $productSign->getProductOfferValue(),
                    $productSign->getProductOfferReference().'_render',
                );

                
                /** Множественный вариант */
                $variation = $call->call(
                    $environment,
                    $productSign->getProductVariationValue(),
                    $productSign->getProductVariationReference().'_render',
                );

                $strOffer = $variation ? ' '.trim($variation) : null;
                
                
                /** Модификация множественного варианта */
                $modification = $call->call(
                    $environment,
                    $productSign->getProductModificationValue(),
                    $productSign->getProductModificationReference().'_render',
                );

                $strOffer .= $modification ? ' '.trim($modification) : null;
                $strOffer .= $offer ? ' '.trim($offer) : null;

                $strOffer .= $productSign->getProductOfferPostfix() ? ' '.$productSign->getProductOfferPostfix() : '';
                $strOffer .= $productSign->getProductVariationPostfix() ? ' '.$productSign->getProductVariationPostfix() : '';
                $strOffer .= $productSign->getProductModificationPostfix() ? ' '.$productSign->getProductModificationPostfix() : '';
                
                $productName = $productSign->getProductName().$strOffer;

                $parameters = [
                    'part' => $productSign->getSignPart(),
                    'article' => $productSign->getProductArticle(),
                    'order' => $order->getId(),
                    'product' => $productSign->getProductId(),
                    'offer' => $productSign->getProductOfferConst(),
                    'variation' => $productSign->getProductVariationConst(),
                    'modification' => $productSign->getProductModificationConst()
                ];
                
                $url = 'https://'.$host.$UrlGenerator->generate('products-sign:document.pdf.orders', $parameters);

                fwrite($handle, $productName.PHP_EOL.$url.PHP_EOL);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/plain');
        $response->headers->set('Content-Disposition', 'attachment; filename="Честные_знаки[Заказ №'.$orderEvent->getOrderNumber().'].txt"');

        return $response;
    }
}