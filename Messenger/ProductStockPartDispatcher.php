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

namespace BaksDev\Products\Sign\Messenger;


use BaksDev\Barcode\Writer\BarcodeFormat;
use BaksDev\Barcode\Writer\BarcodeType;
use BaksDev\Barcode\Writer\BarcodeWrite;
use BaksDev\Products\Sign\Repository\ProductSignByOrder\ProductSignByOrderInterface;
use BaksDev\Products\Stocks\Messenger\Part\ProductStockPartMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** Получаем стикер маркировки «Честный знак» для заказа для определенного продукта */
#[AsMessageHandler(priority: 50)]
final readonly class ProductStockPartDispatcher
{
    public function __construct(
        #[Target('productsSignLogger')] private LoggerInterface $logger,
        private ProductSignByOrderInterface $ProductSignByOrderRepository,
        private BarcodeWrite $BarcodeWrite,
    ) {}

    public function __invoke(ProductStockPartMessage $message): void
    {

        /** Получаем честные знаки по заказу на продукцию */
        foreach($message->getOrders() as $order)
        {
            $signs = $this->ProductSignByOrderRepository
                ->forOrder($order->id)
                ->product($message->getProduct())
                ->offer($message->getOfferConst())
                ->variation($message->getVariationConst())
                ->modification($message->getModificationConst())
                ->findAll();

            //            $sticker[(string) $order->id][] = 'Стикер заказа 1';
            //            $sticker[(string) $order->id][] = 'Стикер заказа 2';
            //            $message->addSticker($sticker);

            if(false === $signs || false === $signs->valid())
            {
                continue;
            }

            $sticker = null;

            foreach($signs as $ProductSignByOrderResult)
            {
                $isRenderBarcode = $this->BarcodeWrite
                    ->text($ProductSignByOrderResult->getBigCode())
                    ->type(BarcodeType::DataMatrix)
                    ->format(BarcodeFormat::SVG)
                    ->generate(filename: (string) $ProductSignByOrderResult->getSignId());

                if(false === $isRenderBarcode)
                {
                    $this->logger->critical(
                        sprintf('products-sign: ошибка генерации честного знака заказа %s', $order->number),
                        [self::class.':'.__LINE__, 'ProductSignEventUid' => $ProductSignByOrderResult->getSignId()],
                    );

                    continue;
                }

                $render = $this->BarcodeWrite->render();
                $render = strip_tags($render, ['path']);
                $render = trim($render);

                $this->BarcodeWrite->remove(); // удаляем после чтения

                /** Заменяем префиксы в номерах заказов  */
                $number = str_replace(['Y-', 'O-', 'M-', 'W-'], '', $order->number);

                $sticker[(string) $order->id][$number]['sign'][] = $render;
            }

            $message->addSticker($sticker);

        }
    }
}
