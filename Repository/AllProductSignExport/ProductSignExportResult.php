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

namespace BaksDev\Products\Sign\Repository\AllProductSignExport;

use BaksDev\Field\Pack\Inn\Type\InnField;
use BaksDev\Field\Pack\Kpp\Type\KppField;
use BaksDev\Field\Pack\Okpo\Type\OkpoField;
use BaksDev\Reference\Money\Type\Money;
use DateMalformedStringException;
use DateTimeImmutable;
use JsonException;


/** @see ProductSignExportResult */
final class ProductSignExportResult
{
    private array|null|false $requisite_decode = null;

    public function __construct(
        private readonly string $number,
        private readonly string $requisite,
        private readonly string $delivery_date,
        private readonly string $products,
    ) {}

    public function getOrderNumber(): string
    {
        return $this->number;
    }

    /**
     * @throws JsonException
     */
    private function getRequisite(): array|false
    {
        if(is_null($this->requisite_decode))
        {
            if(empty($this->requisite))
            {
                $this->requisite_decode = false;
                return false;
            }

            if(false === json_validate($this->requisite))
            {
                $this->requisite_decode = false;
                return false;
            }

            $this->requisite_decode = json_decode($this->requisite, false, 512, JSON_THROW_ON_ERROR);
        }

        return $this->requisite_decode;
    }

    /**
     * @throws JsonException
     */
    public function getInn(): int|false
    {
        $requisite = $this->getRequisite();

        $field = array_filter($requisite, static fn($element) => $element->type === InnField::TYPE);

        $current = current($field);

        return $current ? (int) $current->value : false;
    }

    /**
     * @throws JsonException
     */
    public function getKpp(): int|false
    {
        $requisite = $this->getRequisite();

        $field = array_filter($requisite, static fn($element) => $element->type === KppField::TYPE);

        $current = current($field);

        return $current ? (int) $current->value : false;
    }

    /**
     * @throws JsonException
     */
    public function getOkpo(): int|false
    {
        $requisite = $this->getRequisite();

        $field = array_filter($requisite, static fn($element) => $element->type === OkpoField::TYPE);

        $current = current($field);

        return $current ? (int) $current->value : false;
    }


    /**
     * @throws DateMalformedStringException
     */
    public function getDeliveryDate(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->delivery_date);
    }

    /**
     * @throws JsonException
     */
    public function getOrderTotalPrice(): Money
    {
        $price = 0;

        foreach($this->getProducts() as $product)
        {
            $price += $product->price;
        }

        return new Money($price, true);
    }

    /**
     * @return array<int, object<'code', string, 'price', int, 'article', string >>|false
     * @throws JsonException
     */
    public function getProducts(): array|false
    {
        if(empty($this->products))
        {
            return false;
        }

        if(false === json_validate($this->products))
        {
            return false;
        }

        return json_decode($this->products, false, 512, JSON_THROW_ON_ERROR);
    }
}