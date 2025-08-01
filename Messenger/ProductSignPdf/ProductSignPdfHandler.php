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
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Files\Resources\Messenger\Request\Images\CDNUploadImageMessage;
use BaksDev\Products\Sign\Entity\Code\ProductSignCode;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Type\Id\ProductSignUid;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusError;
use BaksDev\Products\Sign\UseCase\Admin\New\ProductSignDTO;
use BaksDev\Products\Sign\UseCase\Admin\New\ProductSignHandler;
use BaksDev\Products\Stocks\UseCase\Admin\Purchase\Products\ProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Purchase\PurchaseProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Purchase\PurchaseProductStockHandler;
use BaksDev\Users\Profile\UserProfile\Repository\UserByUserProfile\UserByUserProfileInterface;
use DateTimeImmutable;
use DirectoryIterator;
use Doctrine\ORM\Mapping\Table;
use Imagick;
use ImagickPixel;
use Psr\Log\LoggerInterface;
use ReflectionAttribute;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 1)]
final readonly class ProductSignPdfHandler
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $upload,
        #[Target('productsSignLogger')] private LoggerInterface $logger,
        private ProductSignHandler $productSignHandler,
        private PurchaseProductStockHandler $purchaseProductStockHandler,
        private Filesystem $filesystem,
        private BarcodeRead $barcodeRead,
        private MessageDispatchInterface $messageDispatch,
        private UserByUserProfileInterface $UserByUserProfileInterface

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

        // Директория загрузки файла PDF
        $uploadDir = implode(DIRECTORY_SEPARATOR, $upload);


        //        /**
        //         * Сохраняем все листы PDF в отдельные файлы на случай, если есть непреобразованные
        //         */
        //
        //        /** @var DirectoryIterator $SignPdfFile */
        //        foreach(new DirectoryIterator($uploadDir) as $SignPdfFile)
        //        {
        //            if($SignPdfFile->getExtension() !== 'pdf')
        //            {
        //                continue;
        //            }
        //
        //            /** Пропускаем файлы, которые уже разбиты на страницы */
        //            if(str_starts_with($SignPdfFile->getFilename(), 'page') === true)
        //            {
        //                continue;
        //            }
        //
        //            $process = new Process(['pdftk', $SignPdfFile->getRealPath(), 'burst', 'output', $SignPdfFile->getPath().DIRECTORY_SEPARATOR.uniqid('page_', true).'.%d.pdf']);
        //            $process->mustRun();
        //
        //            /** Удаляем после обработки основной файл PDF */
        //            $this->filesystem->remove($SignPdfFile->getRealPath());
        //        }


        /** Обрабатываем страницы */

        $totalPurchase = 0;

        foreach(new DirectoryIterator($uploadDir) as $SignFile)
        {
            if($SignFile->getExtension() !== 'pdf')
            {
                continue;
            }

            if(str_starts_with($SignFile->getFilename(), 'crop') === false)
            {
                continue;
            }

            if(false === $SignFile->getRealPath() || false === file_exists($SignFile->getRealPath()))
            {
                continue;
            }

            /** Генерируем идентификатор группы для отмены */
            $part = new ProductSignUid()->stringToUuid($SignFile->getPath().(new DateTimeImmutable('now')->format('Ymd')));

            $counter = 0;
            $errors = 0;



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
            $pathCode = null;
            $pathCode[] = $this->upload;
            $pathCode[] = 'public';
            $pathCode[] = 'upload';
            $pathCode[] = $current->getArguments()['name'];
            $pathCode[] = '';

            $dirCode = implode(DIRECTORY_SEPARATOR, $pathCode);

            /** Если директория загрузки не найдена - создаем с правами 0700 */
            $this->filesystem->exists($dirCode) ?: $this->filesystem->mkdir($dirCode);


            /**
             * Открываем PDF для подсчета страниц на случай если их несколько
             */
            $pdfPath = $SignFile->getRealPath();
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
                $Imagick->borderImage(new ImagickPixel("white"), 5, 5);
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

                /** Сканируем честный знак */
                $decode = $this->barcodeRead->decode($fileMove);
                $code = $decode->getText();

                if($decode->isError() || str_starts_with($code, '(00)'))
                {
                    $code = uniqid('error_', true);
                    $ProductSignDTO->setStatus(ProductSignStatusError::class);
                }

                $decode->isError() ? ++$errors : ++$counter;

                /**
                 * Переименовываем директорию по коду честного знака (для уникальности)
                 */

                $scanDirName = md5($code);
                $renameDir = $dirCode.$scanDirName.DIRECTORY_SEPARATOR;

                if($this->filesystem->exists($renameDir) === true)
                {
                    // Удаляем директорию если уже имеется
                    $this->filesystem->remove($dirMove);
                }
                else
                {
                    // переименовываем директорию если не существует
                    $this->filesystem->rename($dirMove, $renameDir);
                }


                /** Присваиваем результат сканера */

                $ProductSignCodeDTO = $ProductSignDTO->getCode();
                $ProductSignCodeDTO->setCode($code);
                $ProductSignCodeDTO->setName($scanDirName);
                $ProductSignCodeDTO->setExt('png');

                $ProductSignInvariableDTO = $ProductSignDTO->getInvariable();
                $ProductSignInvariableDTO->setPart($part);
                $ProductSignInvariableDTO->setUsr($message->getUsr());

                $ProductSignInvariableDTO->setProfile($message->getProfile());
                $ProductSignInvariableDTO->setSeller($message->isNotShare() ? $message->getProfile() : null);

                $ProductSignInvariableDTO->setProduct($message->getProduct());
                $ProductSignInvariableDTO->setOffer($message->getOffer());
                $ProductSignInvariableDTO->setVariation($message->getVariation());
                $ProductSignInvariableDTO->setModification($message->getModification());
                $ProductSignInvariableDTO->setNumber($message->getNumber());

                $handle = $this->productSignHandler->handle($ProductSignDTO);

                if(false === ($handle instanceof ProductSign))
                {
                    if($handle === false)
                    {
                        $this->logger->warning(sprintf('Дубликат честного знака %s: ', $code));
                        continue;
                    }

                    $this->logger->critical(sprintf('products-sign: Ошибка %s при сканировании: ', $handle));
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
                        transport: 'files-res-low'
                    );

                    $totalPurchase++;
                }

                //                /** Создаем закупку */
                //                if(isset($PurchaseProductStockDTO) && $message->isPurchase() && $message->getProfile())
                //                {
                //                    // Найдем в коллекции DTO с совпадающими product, offer, variation, modification
                //                    $findPurchaseProduct = $PurchaseProductStockDTO
                //                        ->getProduct()
                //                        ->filter
                //                        (
                //                            function(ProductStockDTO $element) use ($message) {
                //                                return
                //                                    $element->getProduct()->equals($element->getProduct())
                //                                    && ((is_null($element->getOffer()) === true && is_null($message->getOffer()) === true) || $element->getOffer()->equals($message->getOffer()))
                //                                    && ((is_null($element->getVariation()) === true && is_null($message->getVariation()) === true) || $element->getVariation()->equals($message->getVariation()))
                //                                    && ((is_null($element->getModification()) === true && is_null($message->getModification()) === true) || $element->getModification()->equals($message->getModification()));
                //                            }
                //                        );
                //
                //                    $ProductStockDTO = $findPurchaseProduct->current();
                //
                //                    if(false === ($ProductStockDTO instanceof ProductStockDTO))
                //                    {
                //                        $ProductStockDTO = new ProductStockDTO();
                //                        $ProductStockDTO->setProduct($message->getProduct());
                //                        $ProductStockDTO->setOffer($message->getOffer());
                //                        $ProductStockDTO->setVariation($message->getVariation());
                //                        $ProductStockDTO->setModification($message->getModification());
                //                        $ProductStockDTO->setTotal(0);
                //
                //                        $PurchaseProductStockDTO->addProduct($ProductStockDTO);
                //                    }
                //
                //                    $ProductStockTotal = $ProductStockDTO->getTotal() + 1;
                //                    $ProductStockDTO->setTotal($ProductStockTotal);
                //                }
            }

            $Imagick->clear();
        }

        /** Сохраняем закупку */
        if($message->isPurchase())
        {
            // Получаем идентификатор пользователя по профилю
            $User = $this->UserByUserProfileInterface
                ->forProfile($message->getProfile())
                ->find();

            if($User)
            {
                $PurchaseNumber = number_format(microtime(true) * 100, 0, '.', '.');

                $PurchaseProductStockDTO = new PurchaseProductStockDTO();
                $PurchaseProductStocksInvariableDTO = $PurchaseProductStockDTO->getInvariable();

                $PurchaseProductStocksInvariableDTO
                    ->setUsr($User->getId())
                    ->setProfile($message->getProfile())
                    ->setNumber($PurchaseNumber);

                $ProductStockDTO = new ProductStockDTO();
                $ProductStockDTO->setProduct($message->getProduct());
                $ProductStockDTO->setOffer($message->getOffer());
                $ProductStockDTO->setVariation($message->getVariation());
                $ProductStockDTO->setModification($message->getModification());
                $ProductStockDTO->setTotal($totalPurchase);

                $PurchaseProductStockDTO->addProduct($ProductStockDTO);

                $this->purchaseProductStockHandler->handle($PurchaseProductStockDTO);
            }
        }
    }
}
