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

namespace BaksDev\Products\Sign\Messenger\ProductSignStatus;

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Files\Resources\Twig\ImagePathExtension;
use BaksDev\Orders\Order\Messenger\OrderMessage;
use BaksDev\Orders\Order\Repository\OrderProducts\OrderProductResultDTO;
use BaksDev\Orders\Order\Repository\OrderProducts\OrderProductsInterface;
use BaksDev\Products\Sign\Entity\Code\ProductSignCode;
use BaksDev\Products\Sign\Repository\ProductSignByOrder\ProductSignByOrderInterface;
use Doctrine\ORM\Mapping\Table;
use ReflectionAttribute;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Process\Process;

/**
 * Генерируем PDF честных знаков продукции если статус заказа Completed «Выполнен»
 */
#[AsMessageHandler(priority: -100)]
final readonly class ProductSignPdfByOrderCompletedDispatcher
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
        private DeduplicatorInterface $deduplicator,
        private ProductSignByOrderInterface $productSignByOrder,
        private ImagePathExtension $ImagePathExtension,
        private OrderProductsInterface $OrderProducts,

    ) {}

    /**
     * Генерируем PDF если статус заказа Completed «Выполнен»
     */
    public function __invoke(OrderMessage $message): void
    {
        $OrderUid = (string) $message->getId();

        $Deduplicator = $this->deduplicator
            ->namespace('products-sign')
            ->deduplication([
                $OrderUid,
                self::class
            ]);

        if($Deduplicator->isExecuted())
        {
            return;
        }

        /** Получаем идентификаторы заказа для путей генерации */

        $products = $this->OrderProducts
            ->order($OrderUid)
            ->findAllProducts();

        if(false === $products || false === $products->valid())
        {
            return;
        }

        $Deduplicator->save();

        $filesystem = new Filesystem();

        /**
         * Создаем путь для создания PDF файла
         */

        $ref = new ReflectionClass(ProductSignCode::class);
        /** @var ReflectionAttribute $current */
        $current = current($ref->getAttributes(Table::class));
        $dirName = $current->getArguments()['name'] ?? 'barcode';

        /** @var OrderProductResultDTO $product */
        foreach($products as $product)
        {
            $paths[] = $this->projectDir;
            $paths[] = 'public';
            $paths[] = 'upload';
            $paths[] = 'products-sign';
            $paths[] = $dirName;
            $paths[] = $OrderUid;
            $paths[] = (string) $product->getProduct();

            !$product->getProductOfferConst() ?: $paths[] = (string) $product->getProductOfferConst();
            !$product->getProductVariationConst() ?: $paths[] = (string) $product->getProductVariationConst();
            !$product->getProductModificationConst() ?: $paths[] = (string) $product->getProductModificationConst();

            $uploadDir = implode(DIRECTORY_SEPARATOR, $paths);
            $uploadFile = $uploadDir.DIRECTORY_SEPARATOR.'output.pdf';

            if($filesystem->exists($uploadFile))
            {
                $Deduplicator->delete();
                return;
            }

            /**
             * Создаем директорию при отсутствии
             */

            if($filesystem->exists($uploadDir) === false)
            {
                $filesystem->mkdir($uploadDir);
            }

            $codes = $this->productSignByOrder
                ->forOrder($OrderUid)
                ->product($product->getProduct())
                ->offer($product->getProductOfferConst())
                ->variation($product->getProductVariationConst())
                ->modification($product->getProductModificationConst())
                ->findAll();

            if(empty($codes))
            {
                $Deduplicator->delete();
                return;
            }

            /**
             * Формируем запрос на генерацию PDF с массивом изображений
             */

            $Process = null;
            $Process[] = 'convert';

            /** Присваиваем директорию public для локальных файлов */
            $projectDir = implode(DIRECTORY_SEPARATOR, [
                $this->projectDir,
                'public',
                ''
            ]);

            foreach($codes as $code)
            {
                $Process[] = ($code['code_cdn'] === false ? $projectDir : '').$this->ImagePathExtension->imagePath($code['code_image'], $code['code_ext'], $code['code_cdn']);
            }

            $Process[] = $uploadFile;

            $processCrop = new Process($Process);
            $processCrop->mustRun();

        }

        $Deduplicator->delete();
    }
}
