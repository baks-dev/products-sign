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

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Orders\Order\Type\Items\Const\OrderProductItemConst;
use BaksDev\Products\Sign\Entity\Code\ProductSignCode;
use BaksDev\Products\Sign\Entity\Event\ProductSignEvent;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\Collection\ProductSignStatusInterface;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusProcess;
use Doctrine\DBAL\ArrayParameterType;
use InvalidArgumentException;

final class ProductSignByOrderProductItemRepository implements ProductSignByOrderProductItemInterface
{
    private OrderProductItemConst|false $productItem = false;

    private ProductSignStatus|false $status = false;

    private array|false $statuses = false;

    public function __construct(
        private readonly DBALQueryBuilder $DBALQueryBuilder
    ) {}

    /** Единица продукта у которой есть ЧЗ */
    public function forProductItem(OrderProductItemConst $productItem): self
    {
        $this->productItem = $productItem;
        return $this;
    }

    /** Только статус ЧЗ - ProductSignStatusProcess */
    public function forStatusProcess(): self
    {
        $this->status = new ProductSignStatus(ProductSignStatusProcess::class);
        return $this;
    }

    /** Только из переданных статусов ЧЗ */
    public function forStatuses(ProductSignStatus|ProductSignStatusInterface|string $status): self
    {
        if(is_string($status) || $status instanceof ProductSignStatusInterface)
        {
            $status = new ProductSignStatus($status);
        }

        $this->statuses[] = $status;
        return $this;
    }

    /**
     * Информация о Честном знаке
     */
    public function find(): ProductSignByOrderProductItemResult|false
    {
        if(false === ($this->productItem instanceof OrderProductItemConst))
        {
            throw new InvalidArgumentException('Не передан обязательный параметр запроса productItem');
        }

        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $dbal->from(ProductSign::class, 'main');

        $dbal
            ->select('event.status AS status')
            ->join(
                'main',
                ProductSignEvent::class,
                'event',
                '
                    event.id = main.event AND
                    event.product = :productItem
                    ',
            );

        $dbal->setParameter('productItem', $this->productItem, OrderProductItemConst::TYPE);

        $dbal
            ->addSelect('product_sign_code.code AS code')
            ->join(
                'event',
                ProductSignCode::class,
                'product_sign_code',
                'product_sign_code.event = event.id',
            );

        if(true === $this->status instanceof ProductSignStatus)
        {
            $dbal
                ->where('event.status = :status')
                ->setParameter('status', $this->status, ProductSignStatus::TYPE);
        }

        if(false !== $this->statuses)
        {
            $dbal
                ->andWhere('event.status IN (:statuses)')
                ->setParameter('statuses', $this->statuses, ArrayParameterType::STRING);
        }

        return $dbal->fetchHydrate(ProductSignByOrderProductItemResult::class);
    }
}
