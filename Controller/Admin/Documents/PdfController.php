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

namespace BaksDev\Products\Sign\Controller\Admin\Documents;

use BaksDev\Barcode\Writer\BarcodeFormat;
use BaksDev\Barcode\Writer\BarcodeType;
use BaksDev\Barcode\Writer\BarcodeWrite;
use BaksDev\Core\Controller\AbstractController;
use BaksDev\Files\Resources\Twig\ImagePathExtension;
use BaksDev\Products\Sign\Entity\Code\ProductSignCode;
use BaksDev\Products\Sign\Entity\Event\ProductSignEvent;
use BaksDev\Products\Sign\UseCase\Admin\New\ProductSignDTO;
use Doctrine\ORM\Mapping\Table;
use Psr\Log\LoggerInterface;
use ReflectionAttribute;
use ReflectionClass;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Скачивание Честного знака по идентификатору события
 */
#[AsController]
final class PdfController extends AbstractController
{
    #[Route(
        path: '/admin/document/product/sign/pdf/{event}',
        name: 'document.pdf',
        methods: ['GET'])
    ]
    public function pdf(
        #[Target('productsSignLogger')] LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%')] $projectDir,
        #[MapEntity(id: 'event')] ProductSignEvent $event,
        ImagePathExtension $imagePathExtension,
        BarcodeWrite $barcodeWrite,
    ): Response
    {
        $ProductSignDTO = new ProductSignDTO();
        $event->getDto($ProductSignDTO);

        /**
         * Создаем путь для создания PDF файла
         */

        $ref = new ReflectionClass(ProductSignCode::class);

        /** @var ReflectionAttribute $current */
        $current = current($ref->getAttributes(Table::class));
        $dirName = $current->getArguments()['name'] ?? 'barcode';

        $paths = null;
        $paths[] = $projectDir;
        $paths[] = 'public';
        $paths[] = 'upload';
        $paths[] = $dirName;

        $paths[] = $ProductSignDTO->getInvariable()->getPart();

        $filesystem = new Filesystem();

        $uploadDir = implode(DIRECTORY_SEPARATOR, $paths);

        $uploadFile = $uploadDir.DIRECTORY_SEPARATOR.'output.pdf';

        /** Удаляем старый файл */
        if(true === $filesystem->exists($uploadFile))
        {
            $filesystem->remove($uploadFile);
        }

        /** Создаем директорию под новый файл */
        if(false === $filesystem->exists($uploadDir))
        {
            $filesystem->mkdir($uploadDir);
        }

        /**
         * Формируем запрос на генерацию PDF с массивом изображений
         */

        $Process[] = 'convert';

        $ProductSignCodeDTO = $ProductSignDTO->getCode();

        $url = $imagePathExtension->imagePath(
            sprintf('%s%s%s', $dirName, DIRECTORY_SEPARATOR, $ProductSignCodeDTO->getName()),
            $ProductSignCodeDTO->getExt(),
            $ProductSignCodeDTO->getCdn()
        );

        /** Если Честные знаки на CDN */
        if(true === $ProductSignCodeDTO->getCdn())
        {
            $headers = get_headers($url, true);

            if($headers !== false && (str_contains($headers[0], '200') && $headers['Content-Length'] > 100))
            {
                $Process[] = $url;
            }
        }

        /** Если Честные знаки локально */
        if(false === $ProductSignCodeDTO->getCdn())
        {
            /** Присваиваем директорию public для локальных файлов */
            $publicDir = $projectDir.'/public/upload/';

            if(true === file_exists($publicDir.$url))
            {
                $Process[] = $publicDir.$url;
            }
            else
            {
                /** В случае отсутствия файла Честного знака - генерируем из кода, сохраненного в БД */
                $barcodeWrite
                    ->text($ProductSignCodeDTO->getCode())
                    ->type(BarcodeType::DataMatrix)
                    ->format(BarcodeFormat::PNG)
                    ->generate(filename: (string) $event->getMain());

                $path = $barcodeWrite->getPath();

                $Process[] = $path.$event->getMain().'.png';

                $logger->critical(
                    message: sprintf('ошибка изображения %s', $url),
                    context: [$event->getMain()],
                );
            }
        }

        $Process[] = $uploadFile;

        $processCrop = new Process($Process);
        $processCrop->mustRun();

        return new BinaryFileResponse($uploadFile, Response::HTTP_OK)
            ->setContentDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $ProductSignCodeDTO->getName().'['.$ProductSignDTO->getInvariable()->getPart().'].pdf',
            );

    }
}
