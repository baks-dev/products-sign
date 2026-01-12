<?php
/*
 *  Copyright 2026.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Products\Sign\Messenger\ProductSignPdf;

use BaksDev\Barcode\Reader\BarcodeRead;
use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Core\Messenger\MessageDelay;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Files\Resources\Messenger\Request\Images\CDNUploadImageMessage;
use BaksDev\Products\Sign\Messenger\ProductSignPackUpdate\ProductSignPackUpdateMessage;
use BaksDev\Products\Stocks\UseCase\Admin\Purchase\Products\ProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Purchase\PurchaseProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Purchase\PurchaseProductStockHandler;
use BaksDev\Users\Profile\UserProfile\Repository\UserByUserProfile\UserByUserProfileInterface;
use DirectoryIterator;
use Doctrine\ORM\Mapping\Table;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 0)]
final readonly class ProductSignXlsxHandler
{
    public function __construct(
        #[Target('productsSignLogger')] private LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%')] private string $upload,
        private MessageDispatchInterface $MessageDispatch,
        private DeduplicatorInterface $deduplicator,
        private Filesystem $filesystem,
    ) {}

    public function __invoke(ProductSignPdfMessage $message): void
    {
        $upload = null;
        $upload[] = $this->upload;
        $upload[] = 'public';
        $upload[] = 'upload';
        $upload[] = 'barcode';
        $upload[] = 'products-sign';

        $upload[] = (string) $message->getUsr();

        if($message->getProfile())
        {
            $upload[] = (string) $message->getProfile();
        }

        $upload[] = (string) $message->getProduct();

        if($message->getOffer())
        {
            $upload[] = (string) $message->getOffer();
        }

        if($message->getVariation())
        {
            $upload[] = (string) $message->getVariation();
        }

        if($message->getModification())
        {
            $upload[] = (string) $message->getModification();
        }

        $upload[] = '';

        // Директория загрузки файла
        $uploadDir = implode(DIRECTORY_SEPARATOR, $upload);

        if(false === $this->filesystem->exists($uploadDir))
        {
            return;
        }

        /** Обрабатываем файлы EXEL */

        $Deduplicator = $this->deduplicator
            ->namespace('products-sign')
            ->expiresAfter('1 day');

        foreach(new DirectoryIterator($uploadDir) as $SignFileExel)
        {
            if($SignFileExel->getExtension() !== 'xlsx')
            {
                continue;
            }

            if(false === $SignFileExel->getRealPath() || false === file_exists($SignFileExel->getRealPath()))
            {
                continue;
            }

            /**
             * Загружаем файл.
             * IOFactory автоматически определит тип файла (XLSX, XLS, CSV и т.д.)
             * и выберет соответствующий ридер.
             */
            $spreadsheet = IOFactory::load($SignFileExel->getRealPath());

            // Получаем итератор для всех листов в книге
            foreach($spreadsheet->getAllSheets() as $worksheet)
            {
                // Итерируемся по всем строкам листа с помощью итератора
                foreach($worksheet->getRowIterator() as $row)
                {
                    // Получаем номер текущей строки
                    $rowIndex = $row->getRowIndex();

                    // 1. Получаем первую ячейку (колонка B) Код маркировки
                    $cellCode = $worksheet->getCell('B'.$rowIndex);
                    $valueCode = $cellCode->getValue(); // или getCalculatedValue() для формул

                    if(empty($valueCode))
                    {
                        $this->logger->critical(sprintf('products-sign: Код маркировки в строке B:%s не найден', $rowIndex));
                        continue;
                    }

                    $Deduplicator->deduplication([$valueCode]);

                    // Код добавлен в список обработки (либо уже обработан)
                    if($Deduplicator->isExecuted())
                    {
                        continue;
                    }

                    // 2. Получаем вторую ячейку (колонка D) Код упаковки
                    $cellPack = $worksheet->getCell('D'.$rowIndex);
                    $valuePack = $cellPack->getValue();

                    if(empty($valuePack))
                    {
                        $this->logger->critical(sprintf('products-sign: Код упаковки в строке D:%s не найден ', $rowIndex));
                        continue;
                    }

                    /**
                     * Отправляем обновление упаковки в асинхронный транспорт
                     */

                    $ProductSignPackUpdateMessage = new ProductSignPackUpdateMessage(
                        code: $valueCode,
                        pack: $valuePack,
                    );

                    $this->MessageDispatch->dispatch(
                        message: $ProductSignPackUpdateMessage,
                        stamps: [new MessageDelay('1 minutes')],
                        transport: 'barcode-low',
                    );

                    /**
                     * Сохраняем дудубликатор кода, чтобы избежать повторной обработки
                     * Если код не будет найден в базе - он будет удален из дедубликатора для следующего сканера
                     *
                     * @see ProductSignPackUpdateDispatcher
                     */
                    $Deduplicator->save();
                }
            }

            /** Удаляем после обработки файл EXEL */
            $this->filesystem->remove($SignFileExel->getRealPath());
        }
    }
}
