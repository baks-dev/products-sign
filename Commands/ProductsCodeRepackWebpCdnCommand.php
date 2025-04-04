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
use BaksDev\Files\Resources\Messenger\Request\Images\CDNUploadImage;
use BaksDev\Files\Resources\Messenger\Request\Images\CDNUploadImageMessage;
use BaksDev\Products\Sign\Repository\UnCompressProductsCode\UnCompressProductsCodeInterface;
use BaksDev\Products\Sign\Repository\UnCompressProductsCode\UnCompressProductsCodeResult;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'baks:products:code:repack:webp:cdn',
    description: 'Сжатие стикеров Честных знаков продукции'
)]
class ProductsCodeRepackWebpCdnCommand extends Command
{
    public function __construct(
        private readonly UnCompressProductsCodeInterface $UnCompressProductsCode,
        private readonly CDNUploadImage $CDNUploadImage,
        private readonly MessageDispatchInterface $MessageDispatch
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** Получаем все изображения продукта без сжатия */
        $images = $this->UnCompressProductsCode->findAll();

        if(false === $images || false === $images->valid())
        {
            $io->success('Изображений для сжатия не найдено');
            return Command::SUCCESS;
        }

        $helper = $this->getHelper('question');

        /**
         * Интерактивная форма списка профилей
         */

        $questions[] = 'Все';
        $questions['+'] = 'Выполнить все асинхронно';
        $questions['-'] = 'Выйти';

        $question = new ChoiceQuestion(
            question: 'Сжатие стикеров Честных знаков продукции (Ctrl+C чтобы выйти)',
            choices: $questions,
            default: '0'
        );

        $key = $helper->ask($input, $output, $question);

        /**
         * Выходим без выполненного запроса
         */

        if($key === '-' || $key === 'Выйти')
        {
            return Command::SUCCESS;
        }

        $progressBar = new ProgressBar($output);
        $progressBar->start();

        /** @var UnCompressProductsCodeResult $UnCompressMaterialsCodeResult */
        foreach($images as $UnCompressMaterialsCodeResult)
        {
            if(false === class_exists($UnCompressMaterialsCodeResult->getEntity()))
            {
                $io->writeln(sprintf(
                    '<fg=red>Ошибка при сжатии изображения: класс %s не найден</>',
                    $UnCompressMaterialsCodeResult->getEntity()
                ));

                return Command::FAILURE;
            }

            $CDNUploadImageMessage = new CDNUploadImageMessage(
                $UnCompressMaterialsCodeResult->getIdentifier(),
                $UnCompressMaterialsCodeResult->getEntity(),
                $UnCompressMaterialsCodeResult->getName()
            );

            if($key === '0' || $key === 'Все')
            {
                $compress = ($this->CDNUploadImage)($CDNUploadImageMessage);

                if($compress === false)
                {
                    $io->writeln(sprintf(
                            '<fg=red>Ошибка при сжатии изображения %s: %s</>',
                            $UnCompressMaterialsCodeResult->getEntity(),
                            $UnCompressMaterialsCodeResult->getIdentifier())
                    );
                }
            }

            if($key === '+' || $key === 'Выполнить все асинхронно')
            {
                $this->MessageDispatch->dispatch(
                    message: $CDNUploadImageMessage,
                    transport: 'files-res'
                );
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $io->success('Изображения успешно сжаты');

        return Command::SUCCESS;
    }
}
