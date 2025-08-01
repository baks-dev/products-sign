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


use BaksDev\Core\Cache\AppCacheInterface;
use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Products\Sign\Repository\ProductSignByLikeCode\ProductSignByLikeCodeInterface;
use BaksDev\Products\Sign\Repository\UpdateProductSignPack\UpdateProductSignPackInterface;
use BaksDev\Products\Sign\Type\Id\ProductSignUid;
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
        private readonly UpdateProductSignPackInterface $UpdateProductSignPackRepository,
        private readonly DeduplicatorInterface $deduplicator,
        private readonly AppCacheInterface $cache
    ) {}

    public function __invoke(ProductSignPackUpdateMessage $message): void
    {
        $Deduplicator = $this->deduplicator
            ->namespace('products-sign')
            ->deduplication([$message->getCode()]);

        if($Deduplicator->isExecuted())
        {
            return;
        }

        /** Пробуем определить идентификатор честного знака по вхождению */
        $ProductSignUid = $this
            ->ProductSignByLikeCodeRepository
            ->find($message->getCode());

        if(false === ($ProductSignUid instanceof ProductSignUid))
        {
            // Не пишем в лог чтобы не засорять
            //$this->logger->warning(sprintf("products-sign: Код честного знака не найден: %s", $message->getCode()));
            return;
        }

        $isUpdate = $this
            ->UpdateProductSignPackRepository
            ->forProductSign($ProductSignUid)
            ->update($message->getPack());

        if($isUpdate === 1)
        {
            $this->logger->debug(sprintf(
                "%s => %s",
                $ProductSignUid,
                $message->getCode(),
            ));

            $this->cache->init('products-sign')->clear();

            return;
        }

        /** Удаляем дедубликатор для другого сканирования PDF */
        $Deduplicator->delete();
    }
}
