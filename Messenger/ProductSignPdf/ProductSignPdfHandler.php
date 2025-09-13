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
use BaksDev\Products\Sign\Messenger\ProductSignPdf\ProductSignScaner\ProductSignScannerMessage;
use BaksDev\Products\Sign\Type\Id\ProductSignUid;
use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Products\Stocks\UseCase\Admin\Purchase\Products\ProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Purchase\PurchaseProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Purchase\PurchaseProductStockHandler;
use BaksDev\Users\Profile\UserProfile\Repository\UserByUserProfile\UserByUserProfileInterface;
use DateTimeImmutable;
use DirectoryIterator;
use Doctrine\ORM\Mapping\Table;
use Imagick;
use Psr\Log\LoggerInterface;
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
        private PurchaseProductStockHandler $purchaseProductStockHandler,
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


        /** Обрабатываем страницы */

        $totalPurchase = 0;

        Imagick::setResourceLimit(Imagick::RESOURCETYPE_TIME, 3600);

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

            /**
             * Отправляем файл для сканирования
             */

            // Генерируем идентификатор группы для отмены
            $part = new ProductSignUid()->stringToUuid($SignFile->getPath().(new DateTimeImmutable('now')->format('Ymd')));

            $ProductSignScannerMessage = new ProductSignScannerMessage(
                path: $SignFile->getRealPath(),
                part: $part,

                usr: $message->getUsr(),
                profile: $message->getProfile(),
                product: $message->getProduct(),
                offer: $message->getOffer(),
                variation: $message->getVariation(),
                modification: $message->getModification(),

                share: $message->isNotShare(),
                number: $message->getNumber(),

            );

            $this->messageDispatch->dispatch(
                message: $ProductSignScannerMessage,
                transport: 'barcode',
            );

            /**
             * Подсчет количества страниц для создания закупки
             */

            /** Пропускаем, если не требуется создавать закупочный лист */
            if(false === $message->isPurchase())
            {
                continue;
            }

            $pdfPath = $SignFile->getRealPath();

            $Imagick = new Imagick();
            $Imagick->setResolution(50, 50); // устанавливаем малое разрешение
            $Imagick->readImage($pdfPath);

            $totalPurchase += $Imagick->getNumberImages(); // количество страниц в файле

        }

        /** Сохраняем закупку на подгружаемый профиль */
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

                $ProductStockDTO = new ProductStockDTO()
                    ->setProduct($message->getProduct())
                    ->setOffer($message->getOffer())
                    ->setVariation($message->getVariation())
                    ->setModification($message->getModification())
                    ->setTotal($totalPurchase);

                $PurchaseProductStockDTO->addProduct($ProductStockDTO);

                $ProductStock = $this->purchaseProductStockHandler->handle($PurchaseProductStockDTO);

                if(false === ($ProductStock instanceof ProductStock))
                {
                    $this->logger->critical(
                        sprintf('products-sign: Ошибка %s при создании закупочного листа', $ProductStock),
                        [self::class.':'.__LINE__],
                    );
                }

            }
        }
    }
}
