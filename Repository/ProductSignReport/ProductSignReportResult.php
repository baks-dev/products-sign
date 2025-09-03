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

namespace BaksDev\Products\Sign\Repository\ProductSignReport;

use BaksDev\Field\Pack\Inn\Type\InnField;
use BaksDev\Field\Pack\Kpp\Type\KppField;
use BaksDev\Reference\Money\Type\Money;
use DateTimeImmutable;
use Generator;
use JsonException;

final class ProductSignReportResult
{
    private array $items;

    public function __construct(

        private readonly ?string $number,
        private readonly string $date,
        private readonly string $seller,

        //private readonly ?int $total,

        private readonly string $products,

        private readonly ?string $profile_value = null,

    ) {}

    public function getDate(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->date);
    }

    public function getNumber(): string
    {
        return $this->number ?: 'Не указан';
    }


    /**
     * @return Generator<int, ProductSignReportProductDTO>|false
     * @throws JsonException
     */
    public function getProducts(): Generator|false
    {
        if(false === json_validate($this->products))
        {
            return false;
        }

        $items = json_decode($this->products, true, 512, JSON_THROW_ON_ERROR);

        foreach($items as $item)
        {
            yield new ProductSignReportProductDTO(...$item);
        }
    }

    public function getTotalPrice(): Money
    {
        return new Money($this->total, true);
    }


    /**
     * @throws JsonException
     */
    public function getInn(): int
    {
        $default = 100000000;

        if(is_null($this->profile_value))
        {
            return $default;
        }

        if(false === json_validate($this->profile_value))
        {
            return $default;
        }

        $values = array_filter(
            json_decode($this->profile_value, false, 512, JSON_THROW_ON_ERROR),
            static fn($n) => $n->type === InnField::TYPE,
        );

        $value = current($values);

        if(false === $value)
        {
            return $default;
        }

        return $value;


        //        /* TODO: взять из настроек профиля Юр. Лица !!! */
        //
        //        // Turkish Shop
        //        if($this->seller === '01951022-1ba3-7bcc-8583-654b2e001c67')
        //        {
        //            return 5047154781;
        //        }
        //
        //        // Оникс
        //        if($this->seller === '01951024-5f14-72a6-9106-79cc62138b47')
        //        {
        //            return 5047263117;
        //        }
        //
        //        return 100000000;
    }

    /**
     * @throws JsonException
     */
    public function getKpp(): int
    {

        $default = 100000000;

        if(is_null($this->profile_value))
        {
            return $default;
        }

        if(false === json_validate($this->profile_value))
        {
            return $default;
        }

        $values = array_filter(
            json_decode($this->profile_value, false, 512, JSON_THROW_ON_ERROR),
            static fn($n) => $n->type === KppField::TYPE,
        );

        $value = current($values);

        if(false === $value)
        {
            return $default;
        }

        return $value;


        //        // Turkish Shop
        //        if($this->seller === '01951022-1ba3-7bcc-8583-654b2e001c67')
        //        {
        //            return 504701001;
        //        }
        //
        //        // Оникс
        //        if($this->seller === '01951024-5f14-72a6-9106-79cc62138b47')
        //        {
        //            return 504701001;
        //        }
        //
        //
        //        return 1000000000;
    }
}