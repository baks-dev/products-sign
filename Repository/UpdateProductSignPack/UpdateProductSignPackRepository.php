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

namespace BaksDev\Products\Sign\Repository\UpdateProductSignPack;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Sign\Entity\Invariable\ProductSignInvariable;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Type\Id\ProductSignUid;
use InvalidArgumentException;


final class UpdateProductSignPackRepository implements UpdateProductSignPackInterface
{
    private ProductSignUid|false $sign = false;

    public function __construct(private readonly DBALQueryBuilder $DBALQueryBuilder) {}

    public function forProductSign(ProductSignUid|ProductSign $sign): self
    {
        if($sign instanceof ProductSign)
        {
            $sign = $sign->getId();
        }

        $this->sign = $sign;

        return $this;
    }

    /**
     * Присваиваем упаковку коду честного знака
     */
    public function update(string $part): int
    {
        if(false === ($this->sign instanceof ProductSignUid))
        {
            throw new InvalidArgumentException('Invalid Argument ProductSign');
        }

        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $dbal
            ->update(ProductSignInvariable::class)
            ->where('main = :main')
            ->setParameter(
                key: 'main',
                value: $this->sign,
                type: ProductSignUid::TYPE,
            )
            ->set('part', ':part')
            ->setParameter('part', $part);

        return (int) $dbal->executeStatement();
    }
}