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

namespace BaksDev\Products\Sign\Messenger\ProductSignPdf\ProductSignScaner;

use BaksDev\Barcode\Reader\BarcodeRead;
use BaksDev\Core\Messenger\MessageDelay;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Files\Resources\Messenger\Request\Images\CDNUploadImageMessage;
use BaksDev\Products\Product\Repository\ExistProductBarcode\ExistProductBarcodeInterface;
use BaksDev\Products\Product\Repository\Ids\ProductIdsByBarcodesRepository\ProductIdsByBarcodesInterface;
use BaksDev\Products\Product\Repository\Ids\ProductIdsByBarcodesRepository\ProductIdsByBarcodesResult;
use BaksDev\Products\Product\Type\Barcode\ProductBarcode;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Sign\Entity\Code\ProductSignCode;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusError;
use BaksDev\Products\Sign\UseCase\Admin\New\ProductSignDTO;
use BaksDev\Products\Sign\UseCase\Admin\New\ProductSignHandler;
use Doctrine\ORM\Mapping\Table;
use Exception;
use Imagick;
use Psr\Log\LoggerInterface;
use ReflectionAttribute;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Сканирует предварительно разделенные и обрезанные страницы pdf с ЧЗ и сохраняет информацию о них в БД
 */
#[Autoconfigure(shared: false)]
#[AsMessageHandler(priority: 0)]
final readonly class ProductSignScannerDispatcher
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $upload,
        #[Target('productsSignLogger')] private LoggerInterface $logger,
        private MessageDispatchInterface $messageDispatch,
        private ProductSignHandler $productSignHandler,
        private BarcodeRead $barcodeRead,
        private Filesystem $filesystem,
        private ExistProductBarcodeInterface $ExistProductBarcodeRepository,
        private ProductIdsByBarcodesInterface $productIdentifiersByBarcodeRepository,
    ) {}

    public function __invoke(ProductSignScannerMessage $message): void
    {
        /** Файла больше не существует */
        if(false === $this->filesystem->exists($message->getRealPath()))
        {
            return;
        }

        /** Директория загрузки изображения с кодом */

        $ref = new ReflectionClass(ProductSignCode::class);
        /** @var ReflectionAttribute $current */
        $current = current($ref->getAttributes(Table::class));

        if(!isset($current->getArguments()['name']))
        {
            $this->logger->critical(
                sprintf(
                    'Невозможно определить название таблицы из класса сущности %s ',
                    ProductSignCode::class,
                ),
                [self::class.':'.__LINE__],
            );
        }

        /** Создаем полный путь для сохранения изображения с кодом по таблице сущности */
        $pathCode = [
            $this->upload,
            'public',
            'upload',
            $current->getArguments()['name'],
            '',
        ];

        $dirCode = implode(DIRECTORY_SEPARATOR, $pathCode);

        /** Если директория загрузки не найдена - создаем с правами 0700 */
        $this->filesystem->exists($dirCode) ?: $this->filesystem->mkdir($dirCode);

        $pdfPath = $message->getRealPath();

        if(false === $this->filesystem->exists($pdfPath))
        {
            return;
        }

        /**
         * Открываем PDF для подсчета страниц на случай если их несколько
         */


        Imagick::setResourceLimit(Imagick::RESOURCETYPE_MEMORY, (256 * 1024 * 1024));
        Imagick::setResourceLimit(Imagick::RESOURCETYPE_TIME, 3600);

        $Imagick = new Imagick();

        $Imagick->setResolution(500, 500);

        try
        {
            $Imagick->readImage($pdfPath);
        }
        catch(Exception)
        {
            $this->messageDispatch->dispatch(
                message: $message,
                stamps: [new MessageDelay('5 seconds')],
                transport: 'products-sign',
            );

            return;
        }

        $pages = $Imagick->getNumberImages(); // количество страниц в файле

        for($number = 0; $number < $pages; $number++)
        {
            $fileTemp = $dirCode.uniqid('', true).'.png';

            /** Преобразуем PDF страницу в PNG и сохраняем временно для расчета дайджеста md5 */
            $Imagick->setIteratorIndex($number);
            $Imagick->setImageFormat('png');

            $Imagick->writeImage($fileTemp);
            $Imagick->clear();


            /** Рассчитываем дайджест файла для перемещения */
            $md5 = md5_file($fileTemp);
            $dirMove = $dirCode.$md5.DIRECTORY_SEPARATOR;
            $fileMove = $dirMove.'image.png';


            /** Если директория для перемещения не найдена - создаем  */
            $this->filesystem->exists($dirMove) ?: $this->filesystem->mkdir($dirMove);

            /**
             * Перемещаем в указанную директорию если файла не существует
             * Если в перемещаемой директории файл существует - удаляем временный файл
             */
            $this->filesystem->exists($fileMove) === true
                ? $this->filesystem->remove($fileTemp)
                : $this->filesystem->rename($fileTemp, $fileMove);

            /**
             * Создаем для сохранения честный знак
             * в случае ошибки сканирования - присваивается статус с ошибкой
             */
            $ProductSignDTO = new ProductSignDTO();

            /** Сканируем честный знак */
            $decode = $this->barcodeRead
                ->decode($fileMove);

            $code = $decode->getText();

            if($decode->isError() || str_starts_with($code, '(00)'))
            {
                $code = uniqid('error_', true);
                $ProductSignDTO->setStatus(ProductSignStatusError::class);
            }


            /**
             * Необходимо убедиться, что баркод соответствует выбранному продукту, однако только при условии, что ранее
             * данному товару был присвоен баркод
             */
            if(false === $message->isNew())
            {
                /** Пытаемся проверить соответствие продукта и баркода в базе */
                $result = $this->ExistProductBarcodeRepository
                    ->forBarcode(new ProductBarcode($code))
                    ->forProduct($message->getProduct())
                    ->forOffer($message->getOffer())
                    ->forVariation($message->getVariation())
                    ->forModification($message->getModification())
                    ->exist();


                /** Если баркод не соответствует торговому предложению - не сохраняем такой честный знак */
                if(false === $result)
                {
                    $this->logger->warning(
                        sprintf('Баркод %s не соответствует выбранному продукту', $code),
                        [self::class.':'.__LINE__, var_export($message, true)],
                    );


                    /** Удаляем после обработки файл PDF */
                    $this->filesystem->remove($pdfPath);

                    return;
                }
            }


            /**
             * Переименовываем директорию по коду честного знака (для уникальности)
             */

            $scanDirName = md5($code);
            $renameDir = $dirCode.$scanDirName.DIRECTORY_SEPARATOR;

            if(true === $this->filesystem->exists($renameDir))
            {
                // Удаляем директорию если уже имеется
                $this->filesystem->remove($dirMove);
            }
            else
            {
                // переименовываем директорию если не существует
                $this->filesystem->rename($dirMove, $renameDir);
            }


            /**
             * Присваиваем результат сканера
             */

            $ProductSignCodeDTO = $ProductSignDTO->getCode();
            $ProductSignCodeDTO
                ->setCode($code)
                ->setName($scanDirName)
                ->setExt('png');

            $ProductSignInvariableDTO = $ProductSignDTO->getInvariable();

            $ProductSignInvariableDTO
                ->setPart($message->getPart())
                ->setUsr($message->getUsr())
                ->setProfile($message->getProfile())
                ->setSeller($message->isNotShare() ? $message->getProfile() : null)
                ->setNumber($message->getNumber());

            /**
             * Если продукт НЕ ПЕРЕДАН в сообщении - находим его по штрихкоду из файла Честного знака
             */
            if(false === ($message->getProduct() instanceof ProductUid))
            {
                /** Получаем Штрихкод (GTIN) из Честного знака */
                $parseCode = preg_match('/^\(\d+\)(.*?)\(\d+\)/', $code, $matches);

                if(0 === $parseCode || false === $parseCode)
                {
                    $this->logger->critical(
                        message: 'products-sign: Не удалось извлечь штрихкод после сканирования Честного знака. Code: '.$code,
                        context: [self::class.':'.__LINE__, $code],
                    );

                    /** Удаляем файл в случае неудачной обработки */
                    $this->filesystem->remove($pdfPath);

                    return;
                }

                /** Находим продукт по штрихкоду */
                if(1 === $parseCode)
                {
                    /** Код партии */
                    $partCode = $matches[1];
                    $barcodes = [$matches[1]];

                    /** Если штрихкод начинается с 0 - добавляем вариант без 0 */
                    if(str_starts_with($matches[1], '0'))
                    {
                        $barcodes[] = ltrim($matches[1], '0');
                    }

                    /** Продукт по штрихкоду */
                    $product = $this->productIdentifiersByBarcodeRepository
                        ->byBarcodes($barcodes)
                        ->find();

                    /** Присваиваем продукт Честному знаку */
                    if(true === ($product instanceof ProductIdsByBarcodesResult))
                    {
                        $ProductSignInvariableDTO
                            ->setProduct($product->getProduct())
                            ->setOffer($product->getOfferConst())
                            ->setVariation($product->getVariationConst())
                            ->setModification($product->getModificationConst());
                    }

                    /** Если продукт не найден */
                    if(false === ($product instanceof ProductIdsByBarcodesResult))
                    {
                        $this->logger->warning(
                            message: sprintf(
                                'Не удалось найти продукт по штрихкоду %s из Честного знака. Честный знак НЕ БУДЕТ создан',
                                $partCode,
                            ),
                            context: [
                                'штрихкоды' => $barcodes,
                                self::class.':'.__LINE__,
                            ],
                        );

                        /** Удаляем файл в случае неудачной обработки */
                        $this->filesystem->remove($pdfPath);

                        return;
                    }
                }
            }

            /**
             * Если продукт ПЕРЕДАН в сообщении - присваиваем его из сообщения
             */
            if(true === ($message->getProduct() instanceof ProductUid))
            {
                $ProductSignInvariableDTO
                    ->setProduct($message->getProduct())
                    ->setOffer($message->getOffer())
                    ->setVariation($message->getVariation())
                    ->setModification($message->getModification());
            }

            $handle = $this->productSignHandler->handle($ProductSignDTO);

            if(false === ($handle instanceof ProductSign))
            {
                if($handle === false)
                {
                    $this->logger->warning(sprintf('Дубликат честного знака %s: ', $code));

                    /** Удаляем после обработки файл PDF */
                    $this->filesystem->remove($pdfPath);

                    continue;
                }

                $this->logger->critical(
                    sprintf('products-sign: Ошибка %s при сканировании: ', $handle),
                    [self::class.':'.__LINE__],
                );
            }
            else
            {
                $this->logger->info(
                    sprintf('%s: %s', $handle->getId(), $code),
                    [self::class.':'.__LINE__],
                );

                /** Создаем команду для отправки файла CDN */
                $this->messageDispatch->dispatch(
                    new CDNUploadImageMessage($handle->getId(), ProductSignCode::class, $md5),
                    transport: 'files-res-low',
                );

                /** Удаляем после обработки файл PDF */
                $this->filesystem->remove($pdfPath);
            }
        }

    }
}
