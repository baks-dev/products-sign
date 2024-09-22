<?php
/*
 *  Copyright 2024.  Baks.dev <admin@baks.dev>
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
use BaksDev\Barcode\Writer\BarcodeFormat;
use BaksDev\Barcode\Writer\BarcodeType;
use BaksDev\Barcode\Writer\BarcodeWrite;
use BaksDev\Core\Doctrine\ORMQueryBuilder;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Core\Validator\ValidatorCollectionInterface;
use BaksDev\Elastic\Api\Index\ElasticGetIndex;
use BaksDev\Files\Resources\Messenger\Request\Images\CDNUploadImageMessage;
use BaksDev\Files\Resources\Upload\Image\ImageUploadInterface;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\ProductModification;
use BaksDev\Products\Product\Repository\ProductByModification\ProductByModificationInterface;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\Id\ProductModificationUid;
use BaksDev\Products\Sign\Entity\Code\ProductSignCode;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Repository\CurrentEvent\ProductSignCurrentEventInterface;
use BaksDev\Products\Sign\Repository\ExistsProductSignCode\ExistsProductSignCodeInterface;
use BaksDev\Products\Sign\Type\Id\ProductSignUid;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusError;
use BaksDev\Products\Sign\UseCase\Admin\NewEdit\ProductSignDTO;
use BaksDev\Products\Sign\UseCase\Admin\NewEdit\ProductSignHandler;
use BaksDev\Products\Stocks\UseCase\Admin\Purchase\Products\ProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Purchase\PurchaseProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Purchase\PurchaseProductStockHandler;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Type\Id\UserUid;
use DirectoryIterator;
use Doctrine\ORM\Mapping\Table;
use Imagick;
use Psr\Log\LoggerInterface;
use ReflectionAttribute;
use ReflectionClass;
use RuntimeException;
use setasign\Fpdi\Fpdi;
use Smalot\PdfParser\Parser;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Process\Process;

#[AsMessageHandler]
final class ProductSignPdfHandler
{
    private LoggerInterface $logger;

    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $upload,
        private readonly ProductSignHandler $productSignHandler,
        private readonly PurchaseProductStockHandler $purchaseProductStockHandler,
        private readonly Filesystem $filesystem,
        private readonly BarcodeRead $barcodeRead,
        private readonly MessageDispatchInterface $messageDispatch,
        LoggerInterface $productsSignLogger
    ) {

        $this->logger = $productsSignLogger;
    }

    public function __invoke(ProductSignPdfMessage $message): void
    {
        // public/upload/products-sign/

        $upload[] = $this->upload;
        $upload[] = 'public';
        $upload[] = 'upload';
        $upload[] = 'barcode';

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

        // Директория загрузки файла PDF
        $uploadDir = implode(DIRECTORY_SEPARATOR, $upload);


        foreach(new DirectoryIterator($uploadDir) as $SignFile)
        {
            if($SignFile->getExtension() !== 'pdf')
            {
                continue;
            }

            $counter = 0;
            $errors = 0;

            /** Создаем предварительно закупку для заполнения продукции */
            if($message->isPurchase() && $message->getProfile())
            {
                $PurchaseProductStockDTO = new PurchaseProductStockDTO();
                $PurchaseProductStockDTO->setProfile($message->getProfile());
                $PurchaseNumber = number_format(microtime(true) * 100, 0, '.', '.');
                $PurchaseProductStockDTO->setNumber($PurchaseNumber);
            }

            /** Директория загрузки файла с кодом */

            $ref = new ReflectionClass(ProductSignCode::class);
            /** @var ReflectionAttribute $current */
            $current = current($ref->getAttributes(Table::class));

            if(!isset($current->getArguments()['name']))
            {
                $this->logger->critical(
                    sprintf('Невозможно определить название таблицы из класса сущности %s ', ProductSignCode::class),
                    [self::class.':'.__LINE__]
                );
            }

            /** Создаем полный путь для сохранения файла с кодом относительно директории сущности */
            $pathCode[] = $this->upload;
            $pathCode[] = 'public';
            $pathCode[] = 'upload';
            $pathCode[] = $current->getArguments()['name'];
            $pathCode[] = '';

            $dirCode = implode(DIRECTORY_SEPARATOR, $pathCode);

            /** Если директория загрузки не найдена - создаем с правами 0700 */
            $this->filesystem->exists($dirCode) ?: $this->filesystem->mkdir($dirCode);

            /** Генерируем идентификатор группы для отмены */
            $part = new ProductSignUid();


            /**
             * Открываем PDF для подсчета страниц
             */
            $pdfPath = $SignFile->getPathname();
            $Imagick = new Imagick();
            $Imagick->setResolution(500, 500);
            $Imagick->readImage($pdfPath);
            $pages = $Imagick->getNumberImages(); // количество страниц в файле

            /** Удаляем после обработки файл PDF */
            $this->filesystem->remove($pdfPath);

            for($number = 0; $number < $pages; $number++)
            {
                $fileTemp = $dirCode.uniqid('', true).'.png';

                /** Преобразуем PDF страницу в PNG и сохраняем временно для расчета дайджеста md5 */
                $Imagick->setIteratorIndex($number);
                $Imagick->setImageFormat('png');
                $Imagick->writeImage($fileTemp);

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
                $this->filesystem->exists($fileMove) === true ? $this->filesystem->remove($fileTemp) : $this->filesystem->rename($fileTemp, $fileMove);

                /**
                 * Создаем для сохранения честный знак
                 * в случае ошибки сканирования - присваивается статус с ошибкой
                 */
                $ProductSignDTO = new ProductSignDTO();
                $ProductSignDTO->setProfile($message->getProfile());


                /** Сканируем честный знак */
                $decode = $this->barcodeRead->decode($fileMove);
                $code = $decode->getText();

                if($decode->isError())
                {
                    /** Пробуем обрезать изображение по углу и просканировать повторно */
                    $cropWidth = 450;
                    $cropHeight = 500;

                    $x = 55; // Позиция по оси X
                    $y = 200; // Позиция по оси Y

                    // Обрезаем изображение
                    $Imagick->cropImage($cropWidth, $cropHeight, $x, $y);
                    $Imagick->writeImage($fileMove);

                    /** Пробуем считать честный знак с обрезанного файла */
                    $decode = $this->barcodeRead->decode($fileMove);
                    $code = $decode->getText();

                    if($decode->isError())
                    {
                        $code = uniqid('error_', true);
                        $ProductSignDTO->setStatus(ProductSignStatusError::class);
                    }
                }

                $decode->isError() ? ++$errors : ++$counter;


                //$cleanedString = preg_replace('/\((\d+)\)/', '$1', $code);

                /** Присваиваем результат сканера */

                $ProductSignCodeDTO = $ProductSignDTO->getCode();
                $ProductSignCodeDTO->setCode($code);
                $ProductSignCodeDTO->setName($md5);
                $ProductSignCodeDTO->setExt('png');

                $ProductSignInvariableDTO = $ProductSignDTO->getInvariable();
                $ProductSignInvariableDTO->setPart($part);
                $ProductSignInvariableDTO->setUsr($message->getUsr());
                $ProductSignInvariableDTO->setProduct($message->getProduct());
                $ProductSignInvariableDTO->setOffer($message->getOffer());
                $ProductSignInvariableDTO->setVariation($message->getVariation());
                $ProductSignInvariableDTO->setModification($message->getModification());

                $handle = $this->productSignHandler->handle($ProductSignDTO);

                if(!$handle instanceof ProductSign)
                {
                    if($handle === false)
                    {
                        continue;
                    }

                    $this->logger->critical(sprintf('products-sign: Ошибка %s при сканировании PDF лист %s: ', $handle, $number));
                }
                else
                {
                    $this->logger->info(
                        sprintf('%s: %s', $handle->getId(), $code),
                        [self::class.':'.__LINE__]
                    );

                    /** Создаем комманду для отправки файла CDN */
                    $this->messageDispatch->dispatch(
                        new CDNUploadImageMessage($handle->getId(), ProductSignCode::class, $md5),
                        transport: 'files-res'
                    );
                }

                /** Создаем закупку */
                if($message->isPurchase() && $message->getProfile())
                {
                    /** Ищем в массиве такой продукт */
                    $getPurchaseProduct = $PurchaseProductStockDTO->getProduct()->filter(function (
                        ProductStockDTO $element
                    ) use ($message) {
                        return
                            $message->getProduct()->equals($element->getProduct()) &&
                            (
                                ($message->getOffer() === null && $element->getOffer() === null) ||
                                $message->getOffer()->equals($element->getOffer())
                            ) &&

                            (
                                ($message->getVariation() === null && $element->getVariation() === null) ||
                                $message->getVariation()->equals($element->getVariation())
                            ) &&

                            (
                                ($message->getModification() === null && $element->getModification() === null) ||
                                $message->getModification()->equals($element->getModification())
                            );

                    });

                    $ProductStockDTO = $getPurchaseProduct->current();

                    /* если продукта еще нет - добавляем */
                    if(!$ProductStockDTO)
                    {
                        $ProductStockDTO = new ProductStockDTO();
                        $ProductStockDTO->setProduct($message->getProduct());
                        $ProductStockDTO->setOffer($message->getOffer());
                        $ProductStockDTO->setVariation($message->getVariation());
                        $ProductStockDTO->setModification($message->getModification());
                        $ProductStockDTO->setTotal(0);

                        $PurchaseProductStockDTO->addProduct($ProductStockDTO);
                    }

                    $ProductStockTotal = $ProductStockDTO->getTotal() + 1;
                    $ProductStockDTO->setTotal($ProductStockTotal);
                }
            }

            /** Сохраняем закупку */
            if($message->isPurchase() && $message->getProfile() && !$PurchaseProductStockDTO->getProduct()->isEmpty())
            {
                $this->purchaseProductStockHandler->handle($PurchaseProductStockDTO);
            }

            $Imagick->clear();
            $Imagick->destroy();

            if($errors > 0)
            {
                $this->logger->critical(sprintf(
                    'products-sign: Всего добавлено %s из %s честных знаков, c ошибками %s',
                    $counter,
                    $pages,
                    $errors
                ));
            }
            else
            {
                $this->logger->info(sprintf('Всего добавлено %s из %s честных знаков', $counter, $pages));
            }

        }
    }
}
