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

namespace BaksDev\Products\Sign\Commands;


use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Sign\Messenger\ProductSignPdf\ProductSignPdfMessage;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Type\Id\UserUid;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'baks:products:sign:barcode',
    description: 'Запускает сканирование директорий на предмет необработанных PDF'
)]
class ProductsSignBarcodeCommand extends Command
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $project_dir,
        private readonly MessageDispatchInterface $MessageDispatch
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('argument', InputArgument::OPTIONAL, 'Описание аргумента');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $UPLOAD = implode(DIRECTORY_SEPARATOR, [
                $this->project_dir,
                'public',
                'upload',
                'barcode',
                'products-sign'
            ]).DIRECTORY_SEPARATOR;

        $directory = new RecursiveDirectoryIterator($UPLOAD);
        $iterator = new RecursiveIteratorIterator($directory);

        $io = new SymfonyStyle($input, $output);

        $isset = null;

        foreach($iterator as $info)
        {
            if($info->isFile() === false)
            {
                continue;
            }

            if($info->getExtension() !== 'pdf')
            {
                continue;
            }

            $dirPathName = str_replace($UPLOAD, '', $info->getPath());

            $md5 = md5($dirPathName);
            if(isset($isset[$md5]))
            {
                continue;
            }
            $isset[md5($dirPathName)] = $dirPathName;

            /**
             * Создаем DTO из названий директорий UID
             */

            $arrDir = explode(DIRECTORY_SEPARATOR, $dirPathName);

            $ProductSignPdfMessage = new ProductSignPdfMessage(
                isset($arrDir[0]) ? new UserUid($arrDir[0]) : null,
                isset($arrDir[1]) ? new UserProfileUid($arrDir[1]) : null,
                isset($arrDir[2]) ? new ProductUid($arrDir[2]) : null,
                isset($arrDir[3]) ? new ProductOfferConst($arrDir[3]) : null,
                isset($arrDir[4]) ? new ProductVariationConst($arrDir[4]) : null,
                isset($arrDir[5]) ? new ProductModificationConst($arrDir[5]) : null,
                false,
                false,
                'Восстановлен'
            );

            /* Отправляем сообщение в шину для обработки файлов */
            $this->MessageDispatch->dispatch(
                message: $ProductSignPdfMessage,
                transport: 'products-sign'
            );

            $io->writeln(sprintf(
                '<fg=red>Добавили директорию сканирования: %s</>',
                $dirPathName
            ));
        }

        $io->success('Сканирование директорий на предмет необработанных PDF успешно завершено');

        return Command::SUCCESS;
    }
}
