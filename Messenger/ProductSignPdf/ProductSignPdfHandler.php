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

namespace BaksDev\Products\Sign\Messenger\ProductSignPdf;

use BaksDev\Core\Doctrine\ORMQueryBuilder;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Core\Validator\ValidatorCollectionInterface;
use BaksDev\Elastic\Api\Index\ElasticGetIndex;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\ProductModification;
use BaksDev\Products\Product\Repository\ProductByModification\ProductByModificationInterface;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\Id\ProductModificationUid;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Repository\CurrentEvent\ProductSignCurrentEventInterface;
use BaksDev\Products\Sign\Repository\ExistsProductSignCode\ExistsProductSignCodeInterface;
use BaksDev\Products\Sign\UseCase\Admin\NewEdit\ProductSignDTO;
use BaksDev\Products\Sign\UseCase\Admin\NewEdit\ProductSignHandler;
use BaksDev\Products\Stocks\UseCase\Admin\Purchase\Products\ProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Purchase\PurchaseProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Purchase\PurchaseProductStockHandler;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Type\Id\UserUid;
use DirectoryIterator;
use Imagick;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Smalot\PdfParser\Parser;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ProductSignPdfHandler
{
    private string $upload;
    private LoggerInterface $logger;
    private Parser $PDFParser;
    private Imagick $Imagick;
    private ExistsProductSignCodeInterface $existsProductSignCode;
    private ProductSignHandler $productSignHandler;
    private ElasticGetIndex $elasticGetIndex;
    private ProductByModificationInterface $productByModification;
    private PurchaseProductStockHandler $purchaseProductStockHandler;

    public function __construct(
        #[Autowire('%kernel.project_dir%/public/upload/products-sign/')] string $upload,
        ExistsProductSignCodeInterface $existsProductSignCode,
        ProductSignHandler $productSignHandler,
        ElasticGetIndex $elasticGetIndex,
        ProductByModificationInterface $productByModification,
        PurchaseProductStockHandler $purchaseProductStockHandler,
        LoggerInterface $productsSignLogger
    )
    {
        $this->upload = $upload;
        $this->logger = $productsSignLogger;
        $this->existsProductSignCode = $existsProductSignCode;
        $this->productSignHandler = $productSignHandler;
        $this->elasticGetIndex = $elasticGetIndex;
        $this->productByModification = $productByModification;
        $this->purchaseProductStockHandler = $purchaseProductStockHandler;

        $this->PDFParser = new Parser();

        $this->Imagick = new Imagick;
        $this->Imagick->setResolution(200, 200);
    }

    public function __invoke(ProductSignPdfMessage $message): void
    {
        $ProductSignDir = $this->upload.$message->getUsr();

        foreach(new DirectoryIterator($ProductSignDir) as $SignFile)
        {
            if($SignFile->getExtension() !== 'pdf')
            {
                continue;
            }

            $pdfPath = $SignFile->getPathname();
            $pdf = $this->PDFParser->parseFile($pdfPath);
            $pages = $pdf->getPages();

            $this->Imagick->readImage($pdfPath);

            $isRemovePDF = true;

            $PurchaseProductStockDTO = new PurchaseProductStockDTO($message->getProfile());
            $PurchaseNumber = number_format(microtime(true) * 100, 0, '.', '.');
            $PurchaseProductStockDTO->setNumber($PurchaseNumber);

            foreach($pages as $number => $page)
            {

                $arrData = explode(PHP_EOL, $page->getText());
                $filterData = array_filter($arrData, function($value) {
                    return !empty(trim($value));
                });

                $filterData = array_values($filterData);

                $code = $filterData[0].$filterData[1];

                if($this->existsProductSignCode->isExists($message->getUsr(), $code))
                {
                    continue;
                }

                $product = end($filterData);

                // Замена спецсимволов в искомом словосочетании на пробелы
                $product = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $product);
                $product = preg_replace('/\s+/', ' ', $product);
                $product = str_replace('R', ' ', $product);

                /** Поиск по модификации */

                //$result = $this->elasticGetIndex->handle(ProductModification::class, '205 50 17 TH202 93Y', 0);
                $result = $this->elasticGetIndex->handle(ProductModification::class, $product, 0);

                if(false === $result)
                {
                    throw new RuntimeException('Ошибка ElasticSearch');
                }

                /** Количество результатов */
                $counter = $result['hits']['total']['value'];

                if($counter)
                {
                    /* Всегда ищем точное совпадение */
                    if($counter > 1)
                    {
                        /** Создаем честный знак с ошибкой */
                        $isRemovePDF = false;

                        $log = sprintf('Найдено больше одного результата продукции %s', $product);
                        $this->logger->critical($log, [self::class.':'.__LINE__]);

                        continue;
                    }

                    $data = array_column($result['hits']['hits'], "_source");
                    $keys = array_column($data, "id");

                    $ProductModificationUid = new ProductModificationUid(current($keys));
                    $ProductModification = $this->productByModification->findModification($ProductModificationUid);


                    /** Преобразуем PDF страницу в PNG base64 */
                    $this->Imagick->setIteratorIndex($number);
                    $this->Imagick->setImageFormat('png');
                    $imageString = $this->Imagick->getImageBlob();
                    $base64Image = 'data:image/png;base64,'.base64_encode($imageString);


                    /** Сохраняем чистый знак */
                    $ProductSignDTO = new ProductSignDTO($message->getProfile());
                    $ProductSignCodeDTO = $ProductSignDTO->getCode();
                    $ProductSignCodeDTO->setUsr($message->getUsr());
                    $ProductSignCodeDTO->setCode($code);
                    $ProductSignCodeDTO->setQr($base64Image);
                    $ProductSignCodeDTO->setProduct($ProductModification->getProduct());
                    $ProductSignCodeDTO->setOffer($ProductModification->getOfferConst());
                    $ProductSignCodeDTO->setVariation($ProductModification->getVariationConst());
                    $ProductSignCodeDTO->setModification($ProductModification->getModificationConst());

                    $handle = $this->productSignHandler->handle($ProductSignDTO);

                    if(!$handle instanceof ProductSign)
                    {
                        $isRemovePDF = false;
                    }

                    /** Создаем закупку */
                    if($message->isPurchase())
                    {

                        /** Ищем в массиве такой продукт */
                        $getPurchaseProduct = $PurchaseProductStockDTO->getProduct()->filter(function(
                            ProductStockDTO $element
                        ) use ($ProductModification) {
                            return $ProductModification->getModificationConst()?->equals($element->getModification());
                        });

                        $ProductStockDTO = $getPurchaseProduct->current();

                        /* если продукта еще нет - добавляем */
                        if(!$ProductStockDTO)
                        {
                            $ProductStockDTO = new ProductStockDTO();
                            $ProductStockDTO->setProduct($ProductModification->getProduct());
                            $ProductStockDTO->setOffer($ProductModification->getOfferConst());
                            $ProductStockDTO->setVariation($ProductModification->getVariationConst());
                            $ProductStockDTO->setModification($ProductModification->getModificationConst());
                            $ProductStockDTO->setTotal(0);

                            $PurchaseProductStockDTO->addProduct($ProductStockDTO);
                        }

                        $ProductStockTotal = $ProductStockDTO->getTotal() + 1;
                        $ProductStockDTO->setTotal($ProductStockTotal);
                    }

                }
                else
                {

                    $isRemovePDF = false;
                    $log = sprintf('Продукции %s не найдено', $product);
                    $this->logger->critical($log, [self::class.':'.__LINE__]);
                }
            }

            $this->Imagick->clear();
            $this->Imagick->destroy();

            /** Сохраняем закупку */
            if($message->isPurchase() && !$PurchaseProductStockDTO->getProduct()->isEmpty())
            {
                $this->purchaseProductStockHandler->handle($PurchaseProductStockDTO);
            }

            if($isRemovePDF)
            {
                $Filesystem = new Filesystem();
                $Filesystem->remove($pdfPath);
            }

        }
    }
}