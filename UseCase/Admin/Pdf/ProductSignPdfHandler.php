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

namespace BaksDev\Products\Sign\UseCase\Admin\Pdf;


use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Core\Validator\ValidatorCollectionInterface;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Messenger\ProductSignPdf\ProductSignPdfMessage;
use BaksDev\Products\Sign\UseCase\Admin\Pdf\ProductSignFile\ProductSignFileDTO;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;

final class ProductSignPdfHandler
{
    private MessageDispatchInterface $messageDispatch;
    private ValidatorCollectionInterface $validatorCollection;
    private string $upload;
    private LoggerInterface $logger;

    public function __construct(
        #[Autowire('%kernel.project_dir%/public/upload/products-sign/')] string $upload,
        MessageDispatchInterface $messageDispatch,
        ValidatorCollectionInterface $validatorCollection,
        LoggerInterface $productsSignLogger
    )
    {
        $this->messageDispatch = $messageDispatch;
        $this->validatorCollection = $validatorCollection;
        $this->upload = $upload;
        $this->logger = $productsSignLogger;
    }


    /** @see ProductSign */
    public function handle(
        ProductSignPdfDTO $command
    ): string|bool
    {
        $Filesystem = new Filesystem();

        if(!$Filesystem->exists($this->upload))
        {
            try
            {
                $Filesystem->mkdir($this->upload);
            }
            catch(IOExceptionInterface $exception)
            {
                $this->logger->critical('Ошибка при создании директории. Попробуйте применить комманду ',
                    [
                        'mkdir $this->upload && chown -R unit:unit '.$this->upload
                    ]);

                return 'Ошибка при создании директории.';
            }
        }


        // Директория загрузки файла
        $uploadDir = $this->upload.$command->getUsr();

        // проверяем наличие папки, если нет - создаем
        if(!$Filesystem->exists($uploadDir))
        {
            try
            {
                $Filesystem->mkdir($uploadDir);
            }
            catch(IOExceptionInterface $exception)
            {
                $this->logger->critical('Ошибка при создании директории. Попробуйте применить комманду ',
                    [
                        'chown -R unit:unit '.$this->upload
                    ]);

                return 'Ошибка при создании директории.';
            }
        }

        $filename = [];

        /** @var ProductSignFileDTO $file */
        foreach($command->getFiles() as $file)
        {
            $name = uniqid('', true).'.pdf';
            $filename[] = $name;

            $file->pdf->move($uploadDir, $name);

            /** Валидация файла  */
            $this->validatorCollection->add($file->pdf);

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

        /* Отправляем сообщение в шину для обработки файлов */
        $this->messageDispatch->dispatch(
            message: new ProductSignPdfMessage($command->getUsr(), $command->getProfile(), $command->isPurchase()),
            transport: 'product-sign'
        );

        return true;
    }
}