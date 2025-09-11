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

namespace BaksDev\Products\Sign\Messenger\ProductSignPdf\ProductSignScaner;


use BaksDev\Barcode\Reader\BarcodeRead;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Files\Resources\Messenger\Request\Images\CDNUploadImageMessage;
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
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

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
                sprintf('Невозможно определить название таблицы из класса сущности %s ', ProductSignCode::class),
                [self::class.':'.__LINE__],
            );
        }

        /** Создаем полный путь для сохранения изображения с кодом по таблице сущности */
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
        $pdfPath = $message->getRealPath();
        $Imagick = new Imagick();
        $Imagick->setResolution(500, 500);
        $Imagick->readImage($pdfPath);
        $pages = $Imagick->getNumberImages(); // количество страниц в файле

        for($number = 0; $number < $pages; $number++)
        {
            $fileTemp = $dirCode.uniqid('', true).'.png';

            /** Преобразуем PDF страницу в PNG и сохраняем временно для расчета дайджеста md5 */
            $Imagick->setIteratorIndex($number);
            $Imagick->setImageFormat('png');

            /**
             * В некоторых случаях может вызывать ошибку,
             * в таком случае сохраняем без рамки и пробуем отсканировать как есть
             */
            try
            {
                $Imagick->borderImage('white', 5, 5);
            }
            catch(Exception $e)
            {
                $this->logger->critical('products-sign: Ошибка при добавлении рамки к изображению. Пробуем отсканировать как есть.', [$e->getMessage()]);
            }

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
            $decode = $this->barcodeRead
                ->decode($fileMove);

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
                ->setProduct($message->getProduct())
                ->setOffer($message->getOffer())
                ->setVariation($message->getVariation())
                ->setModification($message->getModification())
                ->setNumber($message->getNumber());

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
                    [self::class.':'.__LINE__],
                );

                /** Создаем комманду для отправки файла CDN */
                $this->messageDispatch->dispatch(
                    new CDNUploadImageMessage($handle->getId(), ProductSignCode::class, $md5),
                    transport: 'files-res-low',
                );
            }
        }

        /** Удаляем после обработки файл PDF */
        $this->filesystem->remove($pdfPath);

        $Imagick->clear();

    }
}
