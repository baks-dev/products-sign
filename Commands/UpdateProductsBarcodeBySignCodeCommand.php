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


use BaksDev\Products\Product\Entity\Info\ProductInfo;
use BaksDev\Products\Product\Entity\Offers\ProductOffer;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\ProductModification;
use BaksDev\Products\Product\Entity\Offers\Variation\ProductVariation;
use BaksDev\Products\Product\Repository\AllProductsIdentifier\AllProductsIdentifierInterface;
use BaksDev\Products\Product\Type\Barcode\ProductBarcode;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Id\ProductOfferUid;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Id\ProductVariationUid;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\Id\ProductModificationUid;
use BaksDev\Products\Sign\Repository\BarcodeByProduct\BarcodeByProductInterface;
use BaksDev\Products\Sign\UseCase\Admin\Barcode\ProductBarcode\ProductBarcodeDTO;
use BaksDev\Products\Sign\UseCase\Admin\Barcode\ProductBarcode\ProductBarcodeHandler;
use BaksDev\Products\Sign\UseCase\Admin\Barcode\ProductModificationBarcode\ProductModificationBarcodeDTO;
use BaksDev\Products\Sign\UseCase\Admin\Barcode\ProductModificationBarcode\ProductModificationBarcodeHandler;
use BaksDev\Products\Sign\UseCase\Admin\Barcode\ProductOfferBarcode\ProductOfferBarcodeDTO;
use BaksDev\Products\Sign\UseCase\Admin\Barcode\ProductOfferBarcode\ProductOfferBarcodeHandler;
use BaksDev\Products\Sign\UseCase\Admin\Barcode\ProductVariationBarcode\ProductVariationBarcodeDTO;
use BaksDev\Products\Sign\UseCase\Admin\Barcode\ProductVariationBarcode\ProductVariationBarcodeHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'baks:update:products-barcode:sign-code',
    description: 'Обновляет штрихкоды товаров согласно GTIN честного знака'
)]
class UpdateProductsBarcodeBySignCodeCommand extends Command
{
    public function __construct(
        private readonly AllProductsIdentifierInterface $allProductsIdentifierRepository,
        private readonly BarcodeByProductInterface $BarcodeByProductRepository,

        private readonly ProductBarcodeHandler $ProductBarcodeHandler,
        private readonly ProductOfferBarcodeHandler $ProductOfferBarcodeHandler,
        private readonly ProductVariationBarcodeHandler $ProductVariationBarcodeHandler,
        private readonly ProductModificationBarcodeHandler $ProductModificationBarcodeHandler
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
        $io = new SymfonyStyle($input, $output);

        /** Получаем всю продукцию в системе  */
        $products = $this->allProductsIdentifierRepository->findAll();

        if(false === $products || false === $products->valid())
        {
            $io->warning('Продукции для обновления не найдено');
            return Command::INVALID;
        }

        foreach($products as $ProductsIdentifierResult)
        {
            /** Получаем один честный знак */

            $ProductBarcode = $this->BarcodeByProductRepository
                ->forProduct($ProductsIdentifierResult->getProductId())
                ->forOfferConst($ProductsIdentifierResult->getProductOfferConst())
                ->forVariationConst($ProductsIdentifierResult->getProductVariationConst())
                ->forModificationConst($ProductsIdentifierResult->getProductModificationConst())
                ->find();

            if(false === ($ProductBarcode instanceof ProductBarcode))
            {
                continue;
            }

            /**
             * Обновляем штрихкод модификации множественного варианта торгового предложения
             */
            if($ProductsIdentifierResult->getProductModificationId() instanceof ProductModificationUid)
            {
                $ProductModificationBarcodeDTO = new ProductModificationBarcodeDTO(
                    $ProductsIdentifierResult->getProductModificationId(),
                    $ProductBarcode,
                );

                $ProductModification = $this->ProductModificationBarcodeHandler->handle($ProductModificationBarcodeDTO);

                if(false === ($ProductModification instanceof ProductModification))
                {
                    $io->error('Ошибка %s при обновлении штрихкода ProductModification');
                    return Command::INVALID;
                }

                $io->writeln(sprintf('<fg=green>Обновили штрихкод ProductModification %s</>', $ProductBarcode));

                continue;
            }


            /**
             * Обновляем штрихкод множественного варианта торгового предложения
             */
            if($ProductsIdentifierResult->getProductVariationId() instanceof ProductVariationUid)
            {
                $ProductVariationBarcodeDTO = new ProductVariationBarcodeDTO(
                    $ProductsIdentifierResult->getProductVariationId(),
                    $ProductBarcode,
                );

                $ProductVariation = $this->ProductVariationBarcodeHandler->handle($ProductVariationBarcodeDTO);

                if(false === ($ProductVariation instanceof ProductVariation))
                {
                    $io->error('Ошибка %s при обновлении штрихкода ProductVariation');
                    return Command::INVALID;
                }

                $io->writeln(sprintf('<fg=green>Обновили штрихкод ProductVariation %s</>', $ProductBarcode));

                continue;
            }


            /**
             * Обновляем штрихкод торгового предложения
             */
            if($ProductsIdentifierResult->getProductOfferId() instanceof ProductOfferUid)
            {
                $ProductOfferBarcodeDTO = new ProductOfferBarcodeDTO(
                    $ProductsIdentifierResult->getProductOfferId(),
                    $ProductBarcode,
                );

                $ProductOffer = $this->ProductOfferBarcodeHandler->handle($ProductOfferBarcodeDTO);

                if(false === ($ProductOffer instanceof ProductOffer))
                {
                    $io->error('Ошибка %s при обновлении штрихкода ProductOffer');
                    return Command::INVALID;
                }

                $io->writeln(sprintf('<fg=green>Обновили штрихкод ProductOffer %s</>', $ProductBarcode));

                continue;
            }

            /**
             * Обновляем штрихкод продукта
             */

            $ProductBarcodeDTO = new ProductBarcodeDTO(
                $ProductsIdentifierResult->getProductId(),
                $ProductBarcode,
            );

            $ProductInfo = $this->ProductBarcodeHandler->handle($ProductBarcodeDTO);

            if(false === ($ProductInfo instanceof ProductInfo))
            {
                $io->error('Ошибка %s при обновлении штрихкода ProductInfo');
                return Command::INVALID;
            }

            $io->writeln(sprintf('<fg=green>Обновили штрихкод Product %s</>', $ProductBarcode));
        }


        $io->success('baks:update:products:barcode:by:sign:code');

        return Command::SUCCESS;
    }
}
