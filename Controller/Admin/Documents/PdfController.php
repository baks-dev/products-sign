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

namespace BaksDev\Products\Sign\Controller\Admin\Documents;

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Core\Type\UidType\ParamConverter;
use BaksDev\Files\Resources\Twig\ImagePathExtension;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Sign\Entity\Code\ProductSignCode;
use BaksDev\Products\Sign\Repository\ProductSignByOrder\ProductSignByOrderInterface;
use BaksDev\Products\Sign\Repository\ProductSignByPart\ProductSignByPartInterface;
use BaksDev\Products\Sign\Type\Id\ProductSignUid;
use Doctrine\ORM\Mapping\Table;
use ReflectionAttribute;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[RoleSecurity(['ROLE_ORDERS', 'ROLE_PRODUCT_SIGN'])]
final class PdfController extends AbstractController
{
    private string $projectDir;

    private ImagePathExtension $ImagePathExtension;

    private string $article;

    #[Route('/admin/product/sign/document/pdf/orders/{part}/{article}/{order}/{product}/{offer}/{variation}/{modification}', name: 'document.pdf.orders', methods: ['GET'])]
    public function orders(
        ProductSignByOrderInterface $productSignByOrder,
        ImagePathExtension $ImagePathExtension,
        string $article,
        #[Autowire('%kernel.project_dir%')] $projectDir,
        #[ParamConverter(ProductSignUid::class)] $part,
        #[ParamConverter(OrderUid::class)] OrderUid $order,
        #[ParamConverter(ProductUid::class)] ?ProductUid $product = null,
        #[ParamConverter(ProductOfferConst::class)] ?ProductOfferConst $offer = null,
        #[ParamConverter(ProductVariationConst::class)] ?ProductVariationConst $variation = null,
        #[ParamConverter(ProductModificationConst::class)] ?ProductModificationConst $modification = null,
    ): Response
    {

        $this->projectDir = $projectDir;
        $this->ImagePathExtension = $ImagePathExtension;
        $this->article = $article;

        $codes = $productSignByOrder
            ->forPart($part)
            ->forOrder($order)
            ->product($product)
            ->offer($offer)
            ->variation($variation)
            ->modification($modification)
            ->findAll();

        /**
         * Создаем путь для создания PDF файла
         */

        $ref = new ReflectionClass(ProductSignCode::class);
        /** @var ReflectionAttribute $current */
        $current = current($ref->getAttributes(Table::class));
        $dirName = $current->getArguments()['name'] ?? 'barcode';

        $paths[] = $projectDir;
        $paths[] = 'public';
        $paths[] = 'upload';
        $paths[] = $dirName;

        $paths[] = (string) $order;
        !$part ?: $paths[] = (string) $part;
        !$product ?: $paths[] = (string) $product;
        !$offer ?: $paths[] = (string) $offer;
        !$variation ?: $paths[] = (string) $variation;
        !$modification ?: $paths[] = (string) $modification;

        return $this->BinaryFileResponse($paths, $codes);

    }

    #[Route('/admin/product/sign/document/pdf/parts/{article}/{part}', name: 'document.pdf.parts', methods: ['GET'])]
    public function parts(
        #[Autowire('%kernel.project_dir%')] $projectDir,
        #[ParamConverter(ProductSignUid::class)] $part,
        string $article,
        ProductSignByPartInterface $productSignByPart,
        ImagePathExtension $ImagePathExtension,
    ): Response
    {
        $this->projectDir = $projectDir;
        $this->ImagePathExtension = $ImagePathExtension;
        $this->article = $article;

        $codes = $productSignByPart
            ->forPart($part)
            ->withStatusDone()
            ->findAll();


        /**
         * Создаем путь для создания PDF файла
         */

        $ref = new ReflectionClass(ProductSignCode::class);
        /** @var ReflectionAttribute $current */
        $current = current($ref->getAttributes(Table::class));
        $dirName = $current->getArguments()['name'] ?? 'barcode';

        $paths[] = $projectDir;
        $paths[] = 'public';
        $paths[] = 'upload';
        $paths[] = $dirName;

        $paths[] = (string) $part;

        return $this->BinaryFileResponse($paths, $codes);
    }

    /**
     * Для файлов с большим количество требуется больше времени на генерацию
     * ~ на 1к честных знаков требуется +-1 мин (в веб-сервере нужен соответствующий лимит)
     */
    private function BinaryFileResponse(array $paths, array $codes): BinaryFileResponse
    {
        $filesystem = new Filesystem();

        $uploadDir = implode(DIRECTORY_SEPARATOR, $paths);

        $uploadFile = $uploadDir.DIRECTORY_SEPARATOR.'output.pdf';

        /**
         * Если файл имеется - отдаем
         */

        if($filesystem->exists($uploadFile))
        {
            $filesystem->remove($uploadFile);
        }

        /**
         * Создаем директорию при отсутствии
         */

        if($filesystem->exists($uploadDir) === false)
        {
            $filesystem->mkdir($uploadDir);
        }

        /**
         * Формируем запрос на генерацию PDF с массивом изображений
         */

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


        return new BinaryFileResponse($uploadFile, Response::HTTP_OK)
            ->setContentDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $this->article.'['.count($codes).'].pdf'
            );

    }
}
