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

namespace BaksDev\Products\Sign\Repository\ProductSignByOrder;

use BaksDev\Products\Sign\Type\Event\ProductSignEventUid;
use BaksDev\Products\Sign\Type\Id\ProductSignUid;
use Symfony\Component\Validator\Constraints as Assert;

/** @see ProductSignByOrderResult */
final class ProductSignByOrderResult
{

    public function __construct(
        private readonly string $sign_id, // " => "0195396e-7f26-742d-8fbf-a9ed36a2d029"
        private readonly string $sign_event, // " => "0195b796-9b6c-7654-a557-774d4bede31a"

        private readonly string $code_string,
        // " => "(01)04603766681672(21)5t<!--Za6ucZhTb(91)EE10(92)ej/Cgv8P4Yf7cLJC4Jaf5lc+BEcL6pae05tsT2+TiMc="-->

        private readonly string $code_image, // " => "/upload/product_sign_code/0a58f0ee235e31dc73b5551299a4af88"
        private readonly string $code_ext, // " => "webp"
        private readonly bool $code_cdn, // " => true


    ) {}

    public function getId(): ProductSignUid
    {
        return new ProductSignUid($this->sign_id);
    }

    public function getCodeEvent(): ProductSignEventUid
    {
        return new ProductSignEventUid($this->sign_event);
    }

    public function getCodeSmall(): string
    {
        preg_match('/^(.*?)\(\d{2}\).{4}\(\d{2}\)/', $this->code_string, $matches);


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

        return $this->code_string;

    }

    public function bigCodeBig(): string
    {
        $subChar = "";
        preg_match_all('/\((\d{2})\)((?:(?!\(\d{2}\)).)*)/', $this->code_string, $matches, PREG_SET_ORDER);
        return $matches[0][1].$matches[0][2].$matches[1][1].$matches[1][2].$subChar.$matches[2][1].$matches[2][2].$subChar.$matches[3][1].$matches[3][2];
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

}