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

use BaksDev\Reference\Money\Type\Money;

final class ProductSignReportProductDTO
{

    private readonly array $code;

    public function __construct(

        private readonly string $name,
        private readonly ?int $price,
        private readonly ?int $count,
        private readonly string $article,

        private readonly ?string $offer_value,
        private readonly ?string $offer_reference,

        private readonly ?string $variation_value,
        private readonly ?string $variation_reference,

        private readonly ?string $modification_value,
        private readonly ?string $modification_reference,

        string $code,

    )
    {
        preg_match_all('/\((\d{2})\)((?:(?!\(\d{2}\)).)*)/', $code, $matches, PREG_SET_ORDER);
        $this->code = $matches;
    }

    public function codeSmallFormat(): string
    {
        return $this->code[0][1].$this->code[0][2].$this->code[1][1].$this->code[1][2];
    }

    public function getGtin(): string
    {
        return $this->code[0][2] ?? 'GTIN не определен';
    }


    public function getName(): string
    {
        return $this->name;
    }

    public function getPrice(): Money
    {
        return new Money($this->price, true);
    }

    public function getCount(): int
    {
        return empty($this->count) ? 0 : $this->count;
    }

    public function getAmount(): Money
    {
        $amount = $this->price * $this->count;
        return new Money($amount, true);
    }

    public function getArticle(): string
    {
        return $this->article;
    }

    /** Offer */

    public function getOfferValue(): ?string
    {
        return $this->offer_value;
    }

    public function getOfferReference(): ?string
    {
        return $this->offer_reference;
    }

    /** Variation */

    public function getVariationValue(): ?string
    {
        return $this->variation_value;
    }

    public function getVariationReference(): ?string
    {
        return $this->variation_reference;
    }

    /** Modification */

    public function getModificationValue(): ?string
    {
        return $this->modification_value;
    }

    public function getModificationReference(): ?string
    {
        return $this->modification_reference;
    }
}