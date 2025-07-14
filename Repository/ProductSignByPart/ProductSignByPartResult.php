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

namespace BaksDev\Products\Sign\Repository\ProductSignByPart;

use BaksDev\Products\Sign\Type\Event\ProductSignEventUid;
use BaksDev\Products\Sign\Type\Id\ProductSignUid;
use Symfony\Component\Validator\Constraints as Assert;

/** @see ProductSignByPartResult */
final class ProductSignByPartResult
{


    public function __construct(
        private readonly string $sign_id, //" => "0195a438-28fc-73fe-8f0b-6f589a77a956"
        private readonly string $sign_event, //" => "01967cd2-f1f9-7f96-9d49-fa47a8ca0882"

        private readonly string $code_string,
        //" => "(01)04603766681580(21)5Q6+kt>WODH?+(91)EE10(92)8cLBDlUCIgXd89amACCJyAgmIDFtfItYsVBfh8tjqZI="

        private readonly string $code_image, //" => "/upload/product_sign_code/856095c6a8301ac33c2246f5eab3f192"
        private readonly string $code_ext, //" => "webp"
        private readonly bool $code_cdn, //" => true

    ) {}

    public function getSignId(): ProductSignUid
    {
        return new ProductSignUid($this->sign_id);
    }

    public function getSignEvent(): ProductSignEventUid
    {
        return new ProductSignEventUid($this->sign_event);
    }

    public function getCodeImage(): string
    {
        return $this->code_image;
    }

    public function getCodeExt(): string
    {
        return $this->code_ext;
    }

    public function isCodeCdn(): bool
    {
        return $this->code_cdn === true;
    }

    public function bigCodeBig(): string
    {
        $subChar = "";
        preg_match_all('/\((\d{2})\)((?:(?!\(\d{2}\)).)*)/', $this->code_string, $matches, PREG_SET_ORDER);
        return $matches[0][1].$matches[0][2].$matches[1][1].$matches[1][2].$subChar.$matches[2][1].$matches[2][2].$subChar.$matches[3][1].$matches[3][2];

    }

    public function getCodeSmall(): string
    {
        $code = explode('(91)EE10(92)', $this->code_string);

        // Преобразуем строку в массив символов
        $chars = str_split(current($code));

        // Удаляем символы по указанным позициям (индексы начинаются с 0)

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

        // Собираем строку обратно
        return implode('', $chars);
    }


}