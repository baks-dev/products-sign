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

namespace BaksDev\Products\Sign\Messenger\ProductSignPackUpdate;


use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Core\Messenger\MessageDelay;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Products\Sign\Entity\Invariable\ProductSignInvariable;
use BaksDev\Products\Sign\Repository\ProductSignByLikeCode\ProductSignByLikeCodeInterface;
use BaksDev\Products\Sign\Type\Id\ProductSignUid;
use BaksDev\Products\Sign\UseCase\Admin\Part\UpdateProductSignPartDTO;
use BaksDev\Products\Sign\UseCase\Admin\Part\UpdateProductSignPartHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** Обновляем упаковку по коду маркировки */
#[AsMessageHandler(priority: 0)]
final class ProductSignPackUpdateDispatcher
{
    public function __construct(
        #[Target('productsSignLogger')] private LoggerInterface $logger,
        private readonly ProductSignByLikeCodeInterface $ProductSignByLikeCodeRepository,
        private readonly DeduplicatorInterface $deduplicator,
        private readonly MessageDispatchInterface $messageDispatch,
        private readonly UpdateProductSignPartHandler $UpdateProductSignPartHandler
    ) {}

    public function __invoke(ProductSignPackUpdateMessage $message): void
    {
        $Deduplicator = $this->deduplicator
            ->namespace('products-sign')
            ->deduplication([$message->getCode()]);

        /** Пробуем определить идентификатор честного знака по вхождению */
        $ProductSignUid = $this
            ->ProductSignByLikeCodeRepository
            ->find($message->getCode());

        /** Пробуем повторить попытку через время */
        if(false === ($ProductSignUid instanceof ProductSignUid))
        {
            $this->messageDispatch->dispatch(
                message: $message,
                stamps: [new MessageDelay('1 minutes')],
                transport: 'barcode-low',
            );

            return;
        }

        $UpdateProductSignPartDTO = new UpdateProductSignPartDTO(
            $ProductSignUid,
            $message->getPack(),
        );

        $ProductSignInvariable = $this->UpdateProductSignPartHandler->handle($UpdateProductSignPartDTO);

        if(true === $ProductSignInvariable)
        {
            return;
        }

        if(false === ($ProductSignInvariable instanceof ProductSignInvariable))
        {
            $this->logger->critical(
                'products-sign: Ошибка при обновлении упаковки честного знака',
                [self::class.':'.__LINE__, var_export($UpdateProductSignPartDTO, true)],
            );

            $this->messageDispatch->dispatch(
                message: $message,
                stamps: [new MessageDelay('1 minutes')],
                transport: 'barcode-low',
            );

            return;
        }

        $this->logger->debug(sprintf(
            "%s => %s", $ProductSignUid, $message->getPack(),
        ), [self::class.':'.__LINE__]);

        /** Удаляем дедубликатор для другого сканирования PDF */
        $Deduplicator->delete();
    }
}
