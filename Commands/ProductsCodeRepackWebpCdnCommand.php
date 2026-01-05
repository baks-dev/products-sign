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
 */

declare(strict_types=1);

namespace BaksDev\Products\Sign\Commands;


use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Files\Resources\Messenger\Request\Images\CDNUploadImage;
use BaksDev\Files\Resources\Messenger\Request\Images\CDNUploadImageMessage;
use BaksDev\Products\Sign\Entity\Code\ProductSignCode;
use BaksDev\Products\Sign\Repository\ProductSignCodeByDigest\ProductSignCodeByDigestInterface;
use BaksDev\Products\Sign\Repository\UnCompressProductsCode\UnCompressProductsCodeInterface;
use BaksDev\Products\Sign\Repository\UnCompressProductsCode\UnCompressProductsCodeResult;
use BaksDev\Products\Sign\Type\Id\ProductSignUid;
use Doctrine\ORM\Mapping\Table;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionAttribute;
use ReflectionClass;
use SplFileInfo;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'baks:products:code:repack:webp:cdn',
    description: 'Сжатие стикеров Честных знаков продукции'
)]
class ProductsCodeRepackWebpCdnCommand extends Command
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $upload,
        private readonly UnCompressProductsCodeInterface $UnCompressProductsCode,
        private readonly MessageDispatchInterface $MessageDispatch,
        private readonly ProductSignCodeByDigestInterface $ProductSignCodeByDigest,
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $progressBar = new ProgressBar($output);
        $progressBar->start();

        /**
         * Обрабатываем файлы по базе данных
         */

        $images = $this->UnCompressProductsCode->findAll();

        /** @var UnCompressProductsCodeResult $UnCompressProductsCodeResult */
        foreach($images as $UnCompressProductsCodeResult)
        {
            if(false === class_exists($UnCompressProductsCodeResult->getEntity()))
            {
                $io->writeln(sprintf(
                    '<fg=red>Ошибка при сжатии изображения: класс %s не найден</>',
                    $UnCompressProductsCodeResult->getEntity(),
                ));

                return Command::FAILURE;
            }

            $CDNUploadImageMessage = new CDNUploadImageMessage(
                $UnCompressProductsCodeResult->getIdentifier(),
                $UnCompressProductsCodeResult->getEntity(),
                $UnCompressProductsCodeResult->getName(),
            );

            $this->MessageDispatch->dispatch(
                message: $CDNUploadImageMessage,
                transport: 'files-res',
            );

            $progressBar->advance();
        }


        /**
         * Проверяем директории на признак не пережатых файлов
         */


        /** Выделяем из сущности название таблицы для директории файлов */
        $ref = new ReflectionClass(ProductSignCode::class);

        /** @var ReflectionAttribute $current */
        $current = current($ref->getAttributes(Table::class));
        $TABLE = $current->getArguments()['name'] ?? 'images';

        $upload = null;
        $upload[] = $this->upload;
        $upload[] = 'public';
        $upload[] = 'upload';
        $upload[] = $TABLE;

        $uploadDir = implode(DIRECTORY_SEPARATOR, $upload);

        if(false === is_dir($uploadDir))
        {
            return Command::SUCCESS;
        }

        $iterator = new RecursiveDirectoryIterator($uploadDir, FilesystemIterator::SKIP_DOTS);

        /** @var SplFileInfo $info */
        foreach(new RecursiveIteratorIterator($iterator) as $info)
        {
            /** Удаляем, если в директории имеется файл output.pdf (файл документа) */

            if($info->isFile() && $info->getFilename() === 'output.pdf')
            {
                unlink($info->getRealPath()); // удаляем файл
                rmdir($info->getPath()); // удаляем пустую директорию

                continue;
            }

            /** Определяем файл в базе данных по названию директории */
            $dirName = basename(dirname($info->getRealPath()));
            $ProductSignUid = $this->ProductSignCodeByDigest->find($dirName);

            if(false === $ProductSignUid instanceof ProductSignUid)
            {
                $io->warning(sprintf('Честный знак %s не найден в базе данных', $dirName));

                unlink($info->getRealPath()); // удаляем файл
                rmdir($info->getPath());  // удаляем пустую директорию

                continue;
            }

            $CDNUploadImageMessage = new CDNUploadImageMessage(
                $ProductSignUid,
                ProductSignCode::class,
                $dirName,
            );

            $this->MessageDispatch->dispatch(message: $CDNUploadImageMessage);

            $progressBar->advance();
        }


        $progressBar->finish();
        $io->success('Изображения успешно сжаты');

        return Command::SUCCESS;
    }
}
