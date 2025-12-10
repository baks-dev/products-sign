<?php
/*
 * Copyright 2025.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Products\Sign\Messenger\ProductSignLink;

use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

#[AsMessageHandler]
final readonly class ProductSignLinkDispatcher
{
    public function __construct(
        #[Target('productsSignLogger')] private LoggerInterface $Logger,
        private HttpClientInterface $HttpClient,
    ) {}

    /** Обрабатываем ссылки на скачивание PDF-файлов */
    public function __invoke(ProductSignLinkMessage $message): void
    {
        $linkResponse = $this->HttpClient->request('GET', $message->getLink());

        if(200 !== $linkResponse->getStatusCode())
        {
            $this->Logger->critical(sprintf(
                'Не удается получить файл по ссылке %s: код %s',
                $message->getLink(),
                $linkResponse->getStatusCode()
            ));

            return;
        }

        $contentType = $linkResponse->getHeaders(false)['content-type'][0] ?? null;

        if(empty($contentType))
        {
            return;
        }

        if(
            in_array($contentType, [
                'application/pdf',
                'application/acrobat',
                'application/nappdf',
                'application/x-pdf',
                'image/pdf'
            ])
        )
        {
            $name = uniqid('original_', true).'.pdf';
        }

        if('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' === $contentType)
        {
            $name = uniqid('original_', true).'.xlsx';
        }

        if(empty($name))
        {
            return;
        }

        $fileStream = fopen($message->getUploadDir().$name, 'w');
        foreach($this->HttpClient->stream($linkResponse) as $chunk)
        {
            fwrite($fileStream, $chunk->getContent());
        }

        fclose($fileStream);


        /** Необходимо проверить, что файл действительно был создан */
        $file = fopen($message->getUploadDir().$name, 'r');

        if(empty($file))
        {
            $this->Logger->critical(sprintf(
                'Файл PDF с честным знаком %s не был корректно сохранен',
                $message->getUploadDir().$name
            ));

            return;
        }

        $this->Logger->info(sprintf(
            'Сохранен файл PDF честного знака %s',
            $message->getUploadDir().$name
        ));
    }
}