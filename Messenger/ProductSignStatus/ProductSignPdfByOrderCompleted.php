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
use BaksDev\Products\Sign\Entity\Code\ProductSignCode;
use BaksDev\Products\Sign\Repository\ProductSignByOrder\ProductSignByOrderInterface;
use Doctrine\ORM\Mapping\Table;
use ReflectionAttribute;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Process\Process;

#[AsMessageHandler(priority: -10)]
final readonly class ProductSignPdfByOrderCompleted
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
        private DeduplicatorInterface $deduplicator,
        private ProductSignByOrderInterface $productSignByOrder,
        private ImagePathExtension $ImagePathExtension,

    ) {}

    /**
     * Делаем отметку Честный знак Done «Выполнен» если статус заказа Completed «Выполнен»
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

        $Deduplicator->save();

        $filesystem = new Filesystem();

        /**
         * Создаем путь для создания PDF файла
         */

        $ref = new ReflectionClass(ProductSignCode::class);
        /** @var ReflectionAttribute $current */
        $current = current($ref->getAttributes(Table::class));
        $dirName = $current->getArguments()['name'] ?? 'barcode';

        $uploadDir = implode(DIRECTORY_SEPARATOR, [
            $this->projectDir,
            'public',
            'upload',
            $dirName,
            $OrderUid
        ]);

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
            ->findAll();

        if(empty($codes))
        {
            $Deduplicator->delete();
            return;
        }

        /**
         * Формируем запрос на генерацию PDF с массивом изображений
         */

        $Process[] = 'convert';

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

        $Deduplicator->delete();

    }
}
