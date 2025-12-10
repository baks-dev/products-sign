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

namespace BaksDev\Products\Sign\UseCase\Admin\Pdf;

use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Core\Validator\ValidatorCollectionInterface;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Messenger\ProductSignLink\ProductSignLinkMessage;
use BaksDev\Products\Sign\Messenger\ProductSignPdf\ProductSignPdfMessage;
use BaksDev\Products\Sign\UseCase\Admin\Pdf\ProductSignFile\ProductSignFileDTO;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class ProductSignPdfHandler
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $project_dir,
        #[Target('productsSignLogger')] private LoggerInterface $logger,
        private MessageDispatchInterface $messageDispatch,
        private ValidatorCollectionInterface $validatorCollection,
    ) {}


    /** @see ProductSign */
    public function handle(ProductSignPdfDTO $command): string|bool
    {
        $upload = null;
        $upload[] = $this->project_dir;
        $upload[] = 'public';
        $upload[] = 'upload';
        $upload[] = 'barcode';
        $upload[] = 'products-sign';

        $upload[] = (string) $command->getUsr();

        if($command->getProfile())
        {
            $upload[] = (string) $command->getProfile();
        }

        $upload[] = (string) $command->getProduct();

        if($command->getOffer())
        {
            $upload[] = (string) $command->getOffer();
        }

        if($command->getVariation())
        {
            $upload[] = (string) $command->getVariation();
        }

        if($command->getModification())
        {
            $upload[] = (string) $command->getModification();
        }


        $upload[] = '';

        // Директория загрузки файла
        $uploadDir = implode(DIRECTORY_SEPARATOR, $upload);


        $Filesystem = new Filesystem();

        /** Если директория загрузки не найдена - создаем  */
        if(!$Filesystem->exists($uploadDir))
        {
            try
            {
                $Filesystem->mkdir($uploadDir);
            }
            catch(IOExceptionInterface)
            {
                $this->logger->critical(
                    'Ошибка при создании директории. Попробуйте применить комманду ',
                    [
                        'chown -R unit:unit '.$uploadDir,
                    ],
                );

                return 'Ошибка при создании директории.';
            }
        }


        $filename = [];

        /**
         * @var ProductSignFileDTO $files
         * @var UploadedFile $file
         */

        foreach($command->getFiles() as $files)
        {
            if(true === empty($files->pdf))
            {
                continue;
            }

            foreach($files->pdf as $file)
            {
                $name = null;

                if(
                    in_array($file->getMimeType(), ['application/pdf',
                        'application/acrobat',
                        'application/nappdf',
                        'application/x-pdf',
                        'image/pdf',])
                )
                {
                    $name = uniqid('original_', true).'.pdf';
                }

                if('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' === $file->getMimeType())
                {
                    $name = uniqid('original_', true).'.xlsx';
                }

                if(empty($name))
                {
                    continue;
                }

                $filename[] = $name;

                $file->move($uploadDir, $name);

                /** Валидация файла  */
                $this->validatorCollection->add($file);
            }
        }

        if(false === empty($command->getLinks()))
        {
            /** Получаем (если есть) введенные текстом ссылки на скачивание PDF */
            $text = $command->getLinks();


            /** Разделение текста на строки */
            $lines = preg_split('/\r\n|\r|\n/', $text);

            foreach ($lines as $line)
            {
                /** Если строка является ссылкой - пытаемся скачать  */
                if(str_starts_with($line, 'https:'))
                {
                    $this->messageDispatch->dispatch(new ProductSignLinkMessage(
                        $line,
                        $uploadDir,
                        $command->getUsr(),
                        $command->getProfile(),
                        $command->getProduct(),
                        $command->getOffer(),
                        $command->getVariation(),
                        $command->getModification(),
                        $command->isPurchase(),
                        $command->isNotShare(),
                        $command->getNumber(),
                    ));
                }
            }
        }

        if(empty($filename))
        {
            /**
             * Добавляем в коллекцию dto, т.к. пустая коллекция валидатора (в случае, если файлы не загружались
             * пользователем, а были добавлены через ссылки) не позволит отправить сообщение на сканирование честных знаков
             */
            $this->validatorCollection->add($command);
        }

        /** Валидация всех объектов */
        if($this->validatorCollection->isInvalid())
        {
            foreach($filename as $item)
            {
                $Filesystem->remove($uploadDir.'/'.$item);
            }

            return $this->validatorCollection->getErrorUniqid();
        }


        /** @var ProductSignPdfMessage $message */

        /* Отправляем сообщение в шину для обработки файлов */
        $this->messageDispatch->dispatch(
            message: new ProductSignPdfMessage(
                $command->getUsr(),
                $command->getProfile(),
                $command->getProduct(),
                $command->getOffer(),
                $command->getVariation(),
                $command->getModification(),
                $command->isPurchase(),
                $command->isNotShare(),
                $command->getNumber(),
            ),
            transport: 'products-sign',
        );

        return true;
    }
}
