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

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Delivery\Entity\Delivery;
use BaksDev\Delivery\Type\Id\DeliveryUid;
use BaksDev\Orders\Order\Entity\Invariable\OrderInvariable;
use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Orders\Order\Entity\Products\OrderProduct;
use BaksDev\Orders\Order\Entity\Products\Price\OrderPrice;
use BaksDev\Orders\Order\Entity\User\Delivery\OrderDelivery;
use BaksDev\Orders\Order\Entity\User\OrderUser;
use BaksDev\Products\Category\Entity\CategoryProduct;
use BaksDev\Products\Category\Entity\Offers\CategoryProductOffers;
use BaksDev\Products\Category\Entity\Offers\Variation\CategoryProductVariation;
use BaksDev\Products\Category\Entity\Offers\Variation\Modification\CategoryProductModification;
use BaksDev\Products\Category\Type\Id\CategoryProductUid;
use BaksDev\Products\Product\Entity\Category\ProductCategory;
use BaksDev\Products\Product\Entity\Offers\ProductOffer;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\ProductModification;
use BaksDev\Products\Product\Entity\Offers\Variation\ProductVariation;
use BaksDev\Products\Product\Entity\Product;
use BaksDev\Products\Product\Entity\Trans\ProductTrans;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Sign\Entity\Code\ProductSignCode;
use BaksDev\Products\Sign\Entity\Event\ProductSignEvent;
use BaksDev\Products\Sign\Entity\Invariable\ProductSignInvariable;
use BaksDev\Products\Sign\Entity\Modify\ProductSignModify;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusDecommission;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusDone;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusProcess;
use BaksDev\Users\Profile\TypeProfile\Entity\Section\Fields\TypeProfileSectionField;
use BaksDev\Users\Profile\TypeProfile\Entity\TypeProfile;
use BaksDev\Users\Profile\TypeProfile\Type\Id\TypeProfileUid;
use BaksDev\Users\Profile\UserProfile\Entity\Event\UserProfileEvent;
use BaksDev\Users\Profile\UserProfile\Entity\Event\Value\UserProfileValue;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Types\Types;
use Generator;
use InvalidArgumentException;

final class ProductSignReportRepository implements ProductSignReportInterface
{
    private UserProfileUid|false $profile = false;

    private UserProfileUid|false $seller = false;

    private DateTimeImmutable $from;

    private DateTimeImmutable $to;

    private ProductUid|false $product = false;

    private ProductOfferConst|false $offer = false;

    private ProductVariationConst|false $variation = false;

    private ProductModificationConst|false $modification = false;

    private array|false $status = false;

    private DeliveryUid|false $type = false;

    private CategoryProductUid|false $category = false;

    public function __construct(private readonly DBALQueryBuilder $DBALQueryBuilder)
    {
        $this->from = new DateTimeImmutable('now');
        $this->to = new DateTimeImmutable('now');
    }

    public function fromProfile(UserProfile|UserProfileUid|string $profile): self
    {
        if(empty($profile))
        {
            $this->profile = false;
            return $this;
        }

        if(is_string($profile))
        {
            $profile = new UserProfileUid($profile);
        }

        if($profile instanceof UserProfile)
        {
            $profile = $profile->getId();
        }

        $this->profile = $profile;

        return $this;
    }

    public function fromSeller(UserProfileUid|string $seller): self
    {
        if(empty($seller))
        {
            $this->seller = false;
            return $this;
        }

        if(is_string($seller))
        {
            $seller = new UserProfileUid($seller);
        }

        $this->seller = $seller;

        return $this;
    }


    public function dateFrom(DateTimeImmutable $from): self
    {
        $this->from = $from;

        return $this;
    }

    public function dateTo(DateTimeImmutable $to): self
    {
        $this->to = $to;

        return $this;
    }


    /**
     * Product
     */

    public function setProduct(ProductUid|string|null|false $product): self
    {
        if(empty($product))
        {
            $this->product = false;

            return $this;
        }

        if(is_string($product))
        {
            $product = new ProductUid($product);
        }

        $this->product = $product;

        return $this;
    }

    public function setOffer(ProductOfferConst|string|null|false $offer): self
    {
        if(empty($offer))
        {
            $this->offer = false;
            return $this;
        }

        if(is_string($offer))
        {
            $offer = new ProductOfferConst($offer);
        }

        $this->offer = $offer;

        return $this;
    }

    public function setVariation(ProductVariationConst|string|null|false $variation): self
    {
        if(empty($variation))
        {
            $this->variation = false;
            return $this;
        }

        if(is_string($variation))
        {
            $variation = new ProductVariationConst($variation);
        }

        $this->variation = $variation;

        return $this;
    }

    public function setModification(ProductModificationConst|string|null|false $modification): self
    {
        if(empty($modification))
        {
            $this->modification = false;
            return $this;
        }

        if(is_string($modification))
        {
            $modification = new ProductModificationConst($modification);
        }

        $this->modification = $modification;

        return $this;
    }

    public function onlyStatusDone(): self
    {
        $this->status = [ProductSignStatusDone::STATUS];

        return $this;
    }

    public function onlyStatusProcess(): self
    {
        // Честные знаки со статусом Decommission «Списание» также должны быть в отчете на передачу
        $this->status = [ProductSignStatusProcess::STATUS, ProductSignStatusDecommission::STATUS];

        return $this;
    }

    public function fromProductCategory(CategoryProduct|CategoryProductUid|null|false $category): self
    {
        if(empty($category))
        {
            $this->category = false;
            return $this;
        }

        if($category instanceof CategoryProduct)
        {
            $category = $category->getId();
        }

        $this->category = $category;
        return $this;
    }

    public function orderType(Delivery|DeliveryUid|null|false $type): self
    {
        if(empty($type))
        {
            $this->type = false;
            return $this;
        }

        if($type instanceof Delivery)
        {
            $type = $type->getId();
        }

        $this->type = $type;

        return $this;
    }

    /**
     * Метод получает все реализованные честные знаки (для вывода из оборота)
     *
     * @return Generator<int, ProductSignReportResult>|false
     */
    public function findAll(): Generator|false
    {

        if(false === $this->status)
        {
            throw new InvalidArgumentException('Invalid Argument Status');
        }

        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        $dbal->from(ProductSignInvariable::class, 'invariable');

        /** Если не передан владелец - обязательно должен быть передан Seller для отчета о выводе из оборота (розничная продажа) */

        if(false === ($this->profile instanceof UserProfileUid))
        {
            if(false === ($this->seller instanceof UserProfileUid))
            {
                throw new InvalidArgumentException('Invalid Argument Seller');
            }

            /** Всегда получаем КИЗЫ по продавцу если нет владельца */
            $dbal
                ->andWhere('invariable.seller = :seller')
                ->setParameter(
                    key: 'seller',
                    value: $this->seller,
                    type: UserProfileUid::TYPE,
                );
        }


        /** Если передан Владелец - получаем КИЗЫ для передачи между юр лицам  */
        if($this->profile instanceof UserProfileUid)
        {
            $dbal
                ->andWhere('invariable.profile = :profile')
                ->setParameter(
                    key: 'profile',
                    value: $this->profile,
                    type: UserProfileUid::TYPE,
                );

            /** Получаем все КИЗЫ для передачи */
            if(false === ($this->seller instanceof UserProfileUid))
            {
                $dbal->andWhere('invariable.profile != invariable.seller ');
            }

            /** Получаем КИЗЫ для передачи по продавцу */
            else
            {
                $dbal
                    ->andWhere('invariable.seller = :seller')
                    ->setParameter(
                        key: 'seller',
                        value: $this->seller,
                        type: UserProfileUid::TYPE,
                    );
            }
        }

        $dbal->addSelect('invariable.seller');


        if($this->product)
        {
            $dbal
                ->andWhere('invariable.product = :product')
                ->setParameter(
                    key: 'product',
                    value: $this->product,
                    type: ProductUid::TYPE,
                );
        }

        if($this->offer)
        {
            $dbal
                ->andWhere('invariable.offer = :offer')
                ->setParameter(
                    key: 'offer',
                    value: $this->offer,
                    type: ProductOfferConst::TYPE,
                );
        }

        if($this->variation)
        {
            $dbal
                ->andWhere('invariable.variation = :variation')
                ->setParameter(
                    key: 'variation',
                    value: $this->variation,
                    type: ProductVariationConst::TYPE,
                );
        }

        if($this->modification)
        {
            $dbal
                ->andWhere('invariable.modification = :modification')
                ->setParameter(
                    key: 'modification',
                    value: $this->modification,
                    type: ProductModificationConst::TYPE,
                );
        }


        $dbal
            ->join(
                'invariable',
                ProductSignEvent::class,
                'event',
                'event.main = invariable.main AND event.status IN (:status)',
            )
            ->setParameter(
                key: 'status',
                value: $this->status,
                type: ArrayParameterType::STRING,
            );


        $dbal
            ->addSelect('modify.mod_date AS date')
            ->join(
                'invariable',
                ProductSignModify::class,
                'modify',
                'modify.event = event.id AND DATE(modify.mod_date) BETWEEN :date_from AND :date_to',
            )
            ->setParameter('date_from', $this->from, Types::DATE_IMMUTABLE)
            ->setParameter('date_to', $this->to, Types::DATE_IMMUTABLE);

        $dbal->leftJoin(
            'invariable',
            ProductSignCode::class,
            'code',
            'code.main = invariable.main',
        );


        /** Сырье */

        $dbal->join(
            'invariable',
            Product::class,
            'product',
            'product.id = invariable.product',
        );

        /** Настройки категорий */

        if($this->category instanceof CategoryProductUid)
        {
            $dbal
                ->join(
                    'product',
                    ProductCategory::class,
                    'product_categories_product',
                    'product_categories_product.event = product.event AND product_categories_product.category = :category',
                )
                ->setParameter(
                    key: 'category',
                    value: $this->category,
                    type: CategoryProductUid::TYPE,
                );
        }

        $dbal->join(
            'product',
            ProductTrans::class,
            'product_trans',
            'product_trans.event = product.event AND product_trans.local = :local',
        );

        /** Свойства торговых предложений */

        $dbal->leftJoin(
            'product',
            ProductOffer::class,
            'product_offer',
            'product_offer.event = product.event AND product_offer.const = invariable.offer',
        );

        $dbal
            ->leftJoin(
                'product_offer',
                ProductVariation::class,
                'product_variation',
                'product_variation.offer = product_offer.id AND product_variation.const = invariable.variation',
            );

        $dbal->leftJoin(
            'product_variation',
            ProductModification::class,
            'product_modification',
            'product_modification.variation = product_variation.id AND product_modification.const = invariable.modification',
        );


        $dbal->leftJoin(
            'product_offer',
            CategoryProductOffers::class,
            'category_offer',
            'category_offer.id = product_offer.category_offer',
        );

        $dbal->leftJoin(
            'product_variation',
            CategoryProductVariation::class,
            'category_variation',
            'category_variation.id = product_variation.category_variation',
        );

        $dbal->leftJoin(
            'product_modification',
            CategoryProductModification::class,
            'category_modification',
            'category_modification.id = product_modification.category_modification',
        );


        /** Информация о заказе */

        $dbal->leftJoin(
            'event',
            Order::class,
            'ord',
            'ord.id = event.ord',
        );

        if($this->type instanceof DeliveryUid)
        {
            $dbal->leftJoin(
                'ord',
                OrderUser::class,
                'ord_usr',
                'ord_usr.event = ord.id',
            );

            $dbal
                ->join(
                    'ord_usr',
                    OrderDelivery::class,
                    'order_delivery',
                    'order_delivery.usr = ord_usr.id AND order_delivery.delivery = :delivery',
                )
                ->setParameter(
                    key: 'delivery',
                    value: $this->type,
                    type: DeliveryUid::TYPE,
                );
        }


        $dbal
            ->addSelect('order_invariable.number')
            ->leftJoin(
                'event',
                OrderInvariable::class,
                'order_invariable',
                'order_invariable.main = event.ord',
            );


        $dbal
            ->leftJoin(
                'order_invariable',
                OrderProduct::class,
                'order_product',
                'order_product.event = order_invariable.event',
            );


        $dbal
            ->addSelect('SUM(order_price.price) AS total')
            ->leftJoin(
                'order_product',
                OrderPrice::class,
                'order_price',
                'order_price.product = order_product.id',
            );

        $dbal->addSelect(
            "JSON_AGG
                    ( DISTINCT
        
                            JSONB_BUILD_OBJECT
                            (
                                'name', product_trans.name,
                                
                                'offer_value', product_offer.value,
                                'offer_reference', category_offer.reference,
                                
                                'variation_value', product_variation.value,
                                'variation_reference', category_variation.reference,
                                
                                'modification_value', product_modification.value,
                                'modification_reference', category_modification.reference,
                                
                                'article', COALESCE(
                                    product_modification.article, 
                                    product_variation.article, 
                                    product_offer.article
                                ),

                                'price', order_price.price,
                                'count', order_price.total,
                                'code', code.code
                            )
        
                    ) AS products",
        );


        /**
         * Информация о продавце
         */

        $dbal->leftJoin(
            'invariable',
            UserProfile::class,
            'profile',
            'profile.id = invariable.seller',
        );

        $dbal->leftJoin(
            'profile',
            UserProfileValue::class,
            'profile_value',
            'profile_value.event = profile.event',
        );

        $dbal->leftJoin(
            'profile_value',
            TypeProfileSectionField::class,
            'profile_field',
            'profile_field.id = profile_value.field',
        );

        $dbal->addSelect(
            "JSON_AGG ( DISTINCT

                JSONB_BUILD_OBJECT
                (
                    'value', product_offer.value,
                    'type', profile_field.type
                )

            ) AS profile_value",
        );


        $dbal->allGroupByExclude();


        $result = $dbal->fetchAllHydrate(ProductSignReportResult::class);

        return $result->valid() ? $result : false;
    }
}