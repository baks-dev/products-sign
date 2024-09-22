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

namespace BaksDev\Products\Sign\Commands;

use BaksDev\Core\Doctrine\ORMQueryBuilder;
use BaksDev\Elastic\Api\Index\ElasticGetIndex;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\ProductModification;
use BaksDev\Products\Product\Repository\ProductByModification\ProductByModificationInterface;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\Id\ProductModificationUid;
use BaksDev\Products\Sign\Entity\ProductSign;
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
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(
    name: 'baks:product:sign:pdf:parser',
    description: 'Обрабатываем файлы PDF в директории пользователя и добавляет Честный знак'
)]
class ProductSignPdfParserCommand extends Command
{
    private ExistsProductSignCodeInterface $existsProductSignCode;
    private ProductSignHandler $productSignHandler;
    private ElasticGetIndex $elasticGetIndex;
    private ProductByModificationInterface $productByModification;
    private PurchaseProductStockHandler $purchaseProductStockHandler;
    private LoggerInterface $productsSignLogger;
    private string $upload;

    public function __construct(
        #[Autowire('%kernel.project_dir%/public/upload/products-sign/')] string $upload,
        ExistsProductSignCodeInterface $existsProductSignCode,
        ProductSignHandler $productSignHandler,
        ElasticGetIndex $elasticGetIndex,
        ProductByModificationInterface $productByModification,
        PurchaseProductStockHandler $purchaseProductStockHandler,
        LoggerInterface $productsSignLogger
    ) {
        parent::__construct();
        $this->existsProductSignCode = $existsProductSignCode;
        $this->productSignHandler = $productSignHandler;
        $this->elasticGetIndex = $elasticGetIndex;
        $this->productByModification = $productByModification;
        $this->purchaseProductStockHandler = $purchaseProductStockHandler;
        $this->productsSignLogger = $productsSignLogger;
        $this->upload = $upload;
    }


    protected function configure(): void
    {
        $this
            ->addOption(
                'u',
                'u',
                InputOption::VALUE_REQUIRED,
                'Идентификатор пользователя (--u=... || -u ...)'
            );

        $this
            ->addOption(
                'p',
                'p',
                InputOption::VALUE_REQUIRED,
                'Идентификатор профиля (--p=... || -p ...)'
            );

        $this
            ->addOption(
                'purchase',
                null,
                InputOption::VALUE_OPTIONAL,
                'Идентификатор профиля (--p=... || -p ...)',
                true
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $user = $input->getOption('u');

        if(!$user)
        {
            $io->error('Не указан идентификатор пользователя (--u=... || -u ...)');
            return Command::INVALID;
        }

        $profile = $input->getOption('p');

        if(!$profile)
        {
            $io->error('Не указан идентификатор профиля (--p=... || -p ...)');
            return Command::INVALID;
        }


        $UserUid = new UserUid($user);
        $UserProfileUid = new UserProfileUid($profile);
        $isPurchase = $input->getOption('purchase') === true;
        $ProductSignDir = $this->upload.$UserUid;


        if(!is_dir($ProductSignDir))
        {
            $io->error(sprintf('Директория пользователя %s не найдена', $user));
            return Command::INVALID;
        }


        $progressBar = new ProgressBar($output);
        $progressBar->start();

        // PDFParser
        $PDFParser = new Parser();

        // Imagick
        $Imagick = new Imagick();
        $Imagick->setResolution(200, 200);

        // Filesystem
        $Filesystem = new Filesystem();


        foreach(new DirectoryIterator($ProductSignDir) as $SignFile)
        {
            if($SignFile->getExtension() !== 'pdf')
            {
                continue;
            }

            $pdfPath = $SignFile->getPathname();
            $pdf = $PDFParser->parseFile($pdfPath);
            $pages = $pdf->getPages();

            $Imagick->readImage($pdfPath);

            $isRemovePDF = true;


            if($isPurchase)
            {
                $PurchaseProductStockDTO = new PurchaseProductStockDTO();
                $PurchaseProductStockDTO->setProfile($UserProfileUid);
                $PurchaseNumber = number_format(microtime(true) * 100, 0, '.', '.');
                $PurchaseProductStockDTO->setNumber($PurchaseNumber);
            }


            foreach($pages as $number => $page)
            {

                $arrData = explode(PHP_EOL, $page->getText());
                $filterData = array_filter($arrData, function ($value) {
                    return !empty(trim($value));
                });

                $filterData = array_values($filterData);

                $code = $filterData[0].$filterData[1];

                if($this->existsProductSignCode->isExists($UserUid, $code))
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

                        $message = sprintf('Найдено больше одного результата продукции %s', $product);
                        $io->warning($message);
                        $this->productsSignLogger->critical($message);

                        continue;
                    }

                    $data = array_column($result['hits']['hits'], "_source");
                    $keys = array_column($data, "id");

                    $ProductModificationUid = new ProductModificationUid(current($keys));
                    $ProductModification = $this->productByModification->findModification($ProductModificationUid);


                    /** Преобразуем PDF страницу в PNG base64 */
                    $Imagick->setIteratorIndex($number);
                    $Imagick->setImageFormat('png');
                    $imageString = $Imagick->getImageBlob();
                    $base64Image = 'data:image/png;base64,'.base64_encode($imageString);


                    /** Сохраняем чистый знак */
                    $ProductSignDTO = new ProductSignDTO($UserProfileUid);
                    $ProductSignCodeDTO = $ProductSignDTO->getCode();
                    $ProductSignCodeDTO->setUsr($UserUid);
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

                    $progressBar->advance();

                    if($isPurchase === false)
                    {
                        continue;
                    }

                    /** Ищем в массиве такой продукт */
                    $getPurchaseProduct = $PurchaseProductStockDTO->getProduct()->filter(function (
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
                else
                {
                    $isRemovePDF = false;
                    $message = sprintf('Продукции %s не найдено', $product);
                    $io->warning($message);
                    $this->productsSignLogger->critical($message);
                }
            }

            $Imagick->clear();
            $Imagick->destroy();

            /** Сохраняем закупку */
            if($isPurchase && !$PurchaseProductStockDTO->getProduct()->isEmpty())
            {
                $this->purchaseProductStockHandler->handle($PurchaseProductStockDTO);
            }

            /** Удаляем из директории файл PDF */
            if($isRemovePDF)
            {
                $Filesystem->remove($pdfPath);
            }
        }

        $progressBar->finish();
        $io->success('Обработка файлов успешно завершена');
        return Command::SUCCESS;
    }


    public function randString($length = 10)
    {
        $characters = 'abcdefghijklmnopqrstuvwxyz';
        $charLength = strlen($characters);
        $randomString = '';

        for($i = 0; $i < $length; $i++)
        {
            $randomString .= $characters[random_int(0, $charLength - 1)];
        }

        return $randomString;
    }
}
