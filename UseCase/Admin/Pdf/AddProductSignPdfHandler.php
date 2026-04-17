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

namespace BaksDev\Products\Sign\UseCase\Admin\Pdf;

use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Core\Validator\ValidatorCollectionInterface;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Messenger\ProductSignPdf\ProductSignPdfMessage;
use BaksDev\Products\Sign\UseCase\Admin\Pdf\ProductSignFile\ProductSignFileDTO;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Загружает файлы на сервер и запускает процесс их асинхронной обработки
 */
final readonly class AddProductSignPdfHandler
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
        #[Target('productsSignLogger')] private LoggerInterface $logger,
        private MessageDispatchInterface $messageDispatch,
        private ValidatorCollectionInterface $validatorCollection,
    ) {}

    /** @see ProductSign */
    public function handle(AddProductSignPdfDTO $command): string|bool
    {
        /**
         * Общая директория для всех Честных знаков
         */
        $upload = [
            $this->projectDir,
            'public',
            'upload',
            'barcode',
            'products-sign',
            '',
        ];

        $upload[] = (string) $command->getUsr();

        if(true === $command->getProfile() instanceof UserProfileUid)
        {
            $upload[] = (string) $command->getProfile();
        }

        /** Директория загрузки файла */
        $uploadDir = implode(DIRECTORY_SEPARATOR, $upload);

        $Filesystem = new Filesystem();

        /** Если директория загрузки не найдена - создаем  */
        if(false === $Filesystem->exists($uploadDir))
        {
            try
            {
                $Filesystem->mkdir($uploadDir);
            }
            catch(IOExceptionInterface)
            {
                $this->logger->critical(
                    message: 'Ошибка при создании директории',
                    context: [self::class.':'.__LINE__],
                );

                return false;
            }
        }


        $filename = [];

        /** @var ProductSignFileDTO $files */
        foreach($command->getFiles() as $files)
        {
            if(true === empty($files->pdf))
            {
                continue;
            }

            /** @var UploadedFile $file */
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

                if(null === $name)
                {
                    continue;
                }

                $filename[] = $name;

                $file->move($uploadDir, $name);

                /** Валидация файла  */
                $this->validatorCollection->add($file);
            }
        }

        /** Валидация всех объектов */
        if($this->validatorCollection->isInvalid())
        {
            /** Удаляем загруженный файл */
            foreach($filename as $item)
            {
                $Filesystem->remove($uploadDir.'/'.$item);
            }

            return $this->validatorCollection->getErrorUniqid();
        }

        /**
         * Отправляем сообщение в шину для обработки файлов
         */
        $this->messageDispatch->dispatch(
            message: new ProductSignPdfMessage(
                $command->getUsr(),
                $command->getProfile(),
                $command->getProduct(),
                $command->getOffer(),
                $command->getVariation(),
                $command->getModification(),
                $command->isPurchase(),
                $command->getShare(),
                $command->getNumber(),
                $command->isNew(),
            ),
            transport: 'barcode',
        );

        return true;
    }
}
