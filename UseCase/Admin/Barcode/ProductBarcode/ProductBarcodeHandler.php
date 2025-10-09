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

namespace BaksDev\Products\Sign\UseCase\Admin\Barcode\ProductBarcode;


use BaksDev\Core\Entity\AbstractHandler;
use BaksDev\Products\Product\Entity\Info\ProductInfo;

final class ProductBarcodeHandler extends AbstractHandler
{
    /** @see ProductInfo */
    public function handle(ProductBarcodeDTO $command): string|ProductInfo
    {
        $ProductInfo = $this->getRepository(ProductInfo::class)->find($command->getId());

        if(false === ($ProductInfo instanceof ProductInfo))
        {
            return $this->validatorCollection->getErrorUniqid();
        }

        if(true === $ProductInfo->getBarcode()?->equals($command->getBarcode()))
        {
            return $ProductInfo;
        }

        $this->validatorCollection->add($ProductInfo);

        $this->setCommand($command);

        $ProductInfo->setEntity($command);

        /** Валидация всех объектов */
        if($this->validatorCollection->isInvalid())
        {
            return $this->validatorCollection->getErrorUniqid();
        }

        $this->flush();

        $this->messageDispatch->addClearCacheOther('products-product');

        return $ProductInfo;
    }
}