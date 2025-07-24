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

namespace BaksDev\Products\Sign\Messenger\ProductSignPdf;

use BaksDev\Barcode\Reader\BarcodeRead;
use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Files\Resources\Messenger\Request\Images\CDNUploadImageMessage;
use BaksDev\Products\Sign\Entity\Code\ProductSignCode;
use BaksDev\Products\Sign\Entity\Invariable\ProductSignInvariable;
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
        #[Autowire('%kernel.project_dir%')] private string $upload,
        #[Target('productsSignLogger')] private LoggerInterface $logger,
        private DBALQueryBuilder $DBALQueryBuilder,

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

        /** Обрабатываем файлы EXEL */

        foreach(new DirectoryIterator($uploadDir) as $SignFile)
        {
            if($SignFile->getExtension() !== 'xlsx')
            {
                continue;
            }

            if(false === $SignFile->getRealPath() || false === file_exists($SignFile->getRealPath()))
            {
                continue;
            }

            /**
             * Загружаем файл.
             * IOFactory автоматически определит тип файла (XLSX, XLS, CSV и т.д.)
             * и выберет соответствующий ридер.
             */
            $spreadsheet = IOFactory::load($SignFile->getRealPath());


            // 1. Получаем итератор для всех листов в книге
            foreach($spreadsheet->getAllSheets() as $worksheet)
            {
                $this->logger->info(sprintf("Читаем лист XLSX: %s", $worksheet->getTitle()));

                // Итерируемся по всем строкам листа с помощью итератора
                foreach($worksheet->getRowIterator() as $row)
                {
                    // Получаем номер текущей строки
                    $rowIndex = $row->getRowIndex();

                    // 1. Получаем первую ячейку (колонка B) Код маркировки
                    $cellA = $worksheet->getCell('B'.$rowIndex);
                    $valueA = $cellA->getValue(); // или getCalculatedValue() для формул

                    /**
                     * Определяем по коду честный знак
                     */

                    $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

                    $dbal
                        ->select('code.main')
                        ->from(ProductSignCode::class, 'code')
                        ->where('code.code LIKE :code')
                        ->setParameter(
                            key: 'code',
                            value: $valueA.'%', // поиск по началу кода
                        );

                    $main = $dbal->fetchOne();

                    if(empty($main))
                    {
                        $this->logger->warning(sprintf("products-sign: Код честного знака не найден: %s", $valueA));
                        continue;
                    }


                    // 2. Получаем вторую ячейку (колонка D) Код упаковки
                    $cellB = $worksheet->getCell('D'.$rowIndex);
                    $valueB = $cellB->getValue();


                    /**
                     * Присваиваем мешок коду честного знака
                     */

                    $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

                    $dbal
                        ->update(ProductSignInvariable::class)
                        ->where('main = :main')
                        ->setParameter('main', $main)
                        ->set('part', ':part')
                        ->setParameter('part', $valueB);

                    $dbal->executeStatement();

                    $this->logger->info(sprintf('%s => %s', $main, $valueB));

                }
            }
        }
    }
}
