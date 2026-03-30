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
 *
 */

declare(strict_types=1);

namespace BaksDev\Products\Sign\Messenger\ProductSignPdf\ProductSignPageAndCrop;

use BaksDev\Barcode\Pdf\PdfCropImg;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Products\Sign\Messenger\ProductSignPdf\ProductSignPdfMessage;
use BaksDev\Products\Sign\Messenger\ProductSignPdf\ProductSignScaner\ProductSignScannerMessage;
use BaksDev\Products\Stocks\BaksDevProductsStocksBundle;
use BaksDev\Products\Stocks\Messenger\PurchaseProductStock\PurchaseProductStockMessage;
use BaksDev\Products\Supply\Type\ProductSupplyUid;
use Psr\Log\LoggerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Парсит pdf документ, разбивает его на отдельные файлы с одной страницей и сохраняет как изображение
 */
#[Autoconfigure(shared: false)]
#[AsMessageHandler(priority: 100)]
final readonly class ProductSignPageAndCropDispatcher
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $upload,
        #[Target('productsSignLogger')] private LoggerInterface $logger,
        private Filesystem $filesystem,
        private PdfCropImg $pdfCropImg,
        private MessageDispatchInterface $messageDispatch,
    ) {}

    public function __invoke(ProductSignPdfMessage $message): void
    {
        /**
         * Общая директория для всех Честных знаков
         */
        $upload = [
            $this->upload,
            'public',
            'upload',
            'barcode',
            'products-sign',
        ];

        $upload[] = (string) $message->getUsr();

        if($message->getProfile())
        {
            $upload[] = (string) $message->getProfile();
        }

        if($message->getProduct())
        {
            $upload[] = (string) $message->getProduct();
        }

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

        /** Директория с PDF */
        $uploadDir = implode(DIRECTORY_SEPARATOR, $upload);

        if(false === is_dir($uploadDir))
        {
            $this->logger->critical(
                message: 'products-sign: Неверная директория для обработки Честных знаков',
                context: [
                    self::class.':'.__LINE__,
                    var_export($message, true),
                ],
            );

            return;
        }

        $directory = new RecursiveDirectoryIterator($uploadDir);
        $iterator = new RecursiveIteratorIterator($directory);

        /**
         * Обрабатываем все загруженные pdf в директории
         * @var $info SplFileInfo
         */
        foreach($iterator as $info)
        {
            if(
                false === $info->isFile() ||
                false === $info->getRealPath() ||
                false === ($info->getExtension() === 'pdf') ||
                false === file_exists($info->getRealPath()) ||
                false === str_starts_with($info->getFilename(), 'original')
            )
            {
                continue;
            }

            /** Генерируем идентификатор группы для отмены */
            $part = new ProductSupplyUid();

            /**
             * Парсит pdf документ, разбивает его на отдельные файлы с одной страницей и сохраняет как изображение
             */
            $isCrop = $this->pdfCropImg
                ->path($info->getPath())
                ->filename($info->getFilename())
                ->crop((string) $part);

            if(true === $isCrop)
            {
                /** Количество продукции для закупки */
                $totalPurchase = 0;

                /** Директория с изображениями Честного знака */
                $imgDirPath = $info->getPath().DIRECTORY_SEPARATOR.$part;

                $imgDirectory = new RecursiveDirectoryIterator($imgDirPath);
                $imgIterator = new RecursiveIteratorIterator($imgDirectory);

                /**
                 * Перебираем каждое изображение Честного знака
                 * и запускаем процесс сканирования
                 *
                 * @var $image SplFileInfo
                 */
                foreach($imgIterator as $image)
                {
                    if(
                        false === $image->isFile() ||
                        false === $image->getRealPath() ||
                        false === ($image->getExtension() === 'png') ||
                        false === file_exists($image->getRealPath())
                    )
                    {
                        continue;
                    }

                    $ProductSignScannerMessage = new ProductSignScannerMessage(
                        path: $image->getRealPath(),
                        part: $part,

                        usr: $message->getUsr(),
                        profile: $message->getProfile(),

                        product: $message->getProduct(),
                        offer: $message->getOffer(),
                        variation: $message->getVariation(),
                        modification: $message->getModification(),

                        share: $message->isNotShare(),
                        number: $message->getNumber(),
                        isNew: $message->isNew(),
                    );

                    $this->messageDispatch->dispatch(
                        message: $ProductSignScannerMessage,
                        transport: 'barcode',
                    );

                    $totalPurchase++; // количество страниц в файле
                }

                /**
                 * Если выбрано - Сохраняем закупку на выбранный профиль
                 */
                if(true === $message->isPurchase())
                {

                    if(false === class_exists(BaksDevProductsStocksBundle::class))
                    {
                        $this->logger->warning(
                            message: 'products-sign: Не установлен модуль products-stocks для создании закупочного листа',
                            context: [
                                self::class.':'.__LINE__,
                                var_export($message, true),
                            ],
                        );

                        continue;
                    }

                    $PurchaseProductStockMessage = new PurchaseProductStockMessage(
                        $totalPurchase,
                        $message->getProfile(),
                        $message->getProduct(),
                        $message->getOffer(),
                        $message->getVariation(),
                        $message->getModification(),
                    );

                    $this->messageDispatch->dispatch(
                        message: $PurchaseProductStockMessage,
                        transport: 'products-stocks',
                    );
                }
            }

            /**
             * В случае ошибки - логгируем в critical
             */
            if(false === $isCrop)
            {
                $this->logger->critical(
                    message: 'products-sign: Не удалось преобразовать pdf',
                    context: [
                        self::class.':'.__LINE__,
                        $info->getRealPath(),
                        $part,
                        var_export($message, true),
                    ],
                );
            }

            /** Удаляем после обработки файл PDF */
            $this->filesystem->remove($info->getRealPath());
        }
    }
}
