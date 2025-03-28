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

namespace BaksDev\Products\Sign\Messenger\ProductSignStatus\ProductSignDone;


use BaksDev\Products\Sign\Entity\Event\ProductSignEvent;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Repository\ProductSignProcessByOrderProduct\ProductSignProcessByOrderProductInterface;
use BaksDev\Products\Sign\UseCase\Admin\Status\ProductSignDoneDTO;
use BaksDev\Products\Sign\UseCase\Admin\Status\ProductSignStatusHandler;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 0)]
final readonly class ProductSignDoneDispatcher
{
    public function __construct(
        #[Target('productsSignLogger')] private LoggerInterface $logger,
        private ProductSignProcessByOrderProductInterface $productSignProcessByOrderProduct,
        private ProductSignStatusHandler $productSignStatusHandler,
    ) {}

    public function __invoke(ProductSignDoneMessage $message): void
    {
        $ProductSignEvent = $this->productSignProcessByOrderProduct
            ->forOrder($message->getOrder())
            ->forProduct($message->getProduct())
            ->forOfferConst($message->getOfferConst())
            ->forVariationConst($message->getVariationConst())
            ->forModificationConst($message->getModificationConst())
            ->find();

        if(false === ($ProductSignEvent instanceof ProductSignEvent))
        {
            $this->logger->critical(
                'products-sign: Честный знак на продукцию на найден',
                [var_export($message, true), self::class.':'.__LINE__]
            );

            return;
        }

        $ProductSignDoneDTO = new ProductSignDoneDTO();
        $ProductSignEvent->getDto($ProductSignDoneDTO);

        $handle = $this->productSignStatusHandler->handle($ProductSignDoneDTO);

        if(false === ($handle instanceof ProductSign))
        {
            $this->logger->critical(
                sprintf('%s: Ошибка при обновлении статуса честного знака', $handle),
                [
                    'ProductSignEventUid' => $ProductSignDoneDTO->getEvent(),
                    self::class.':'.__LINE__,
                ]
            );

            throw new InvalidArgumentException('Ошибка при обновлении статуса честного знака');
        }

        $this->logger->info(
            'Отметили Честный знак Done «Выполнен»',
            [
                'ProductSignUid' => $ProductSignEvent->getMain(),
                self::class.':'.__LINE__,
            ]
        );

    }
}
