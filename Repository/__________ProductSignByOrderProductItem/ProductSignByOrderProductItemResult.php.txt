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

namespace BaksDev\Products\Sign\Repository\ProductSignByOrderProductItem;

use BaksDev\Products\Sign\Type\Status\ProductSignStatus;

final readonly class ProductSignByOrderProductItemResult
{
    public function __construct(
        private string $code,
        private string $status,
    ) {}

    public function getCode(): string
    {
        return $this->code;
    }

    public function getSmallCode(): string
    {
        preg_match('/^(.*?)\(\d{2}\).{4}\(\d{2}\)/', $this->code, $matches);

        if(isset($matches[1]))
        {
            // Преобразуем строку в массив символов
            $chars = str_split($matches[1]);

            // 1 символ (индекс 0)
            if($chars[0] === '(')
            {
                unset($chars[0]);
            }

            // 4 символ (индекс 3)
            if($chars[3] === ')')
            {
                unset($chars[3]);
            }


            // 19 символ (индекс 18)
            if($chars[18] === '(')
            {
                unset($chars[18]);
            }

            // 22 символ (индекс 21)
            if($chars[21] === ')')
            {
                unset($chars[21]);
            }

            return implode('', $chars);

        }

        return $this->code;
    }

    public function getBigCode(): string
    {
        $subChar = "";
        preg_match_all('/\((\d{2})\)((?:(?!\(\d{2}\)).)*)/', $this->code, $matches, PREG_SET_ORDER);
        return $matches[0][1].$matches[0][2].$matches[1][1].$matches[1][2].$subChar.$matches[2][1].$matches[2][2].$subChar.$matches[3][1].$matches[3][2];
    }

    public function getStatus(): ProductSignStatus
    {
        return new ProductSignStatus($this->status);
    }
}