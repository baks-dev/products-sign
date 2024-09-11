<?php
/*
 *  Copyright 2024.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Products\Sign\Repository\NotExistsProductSignCode;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Services\Paginator\PaginatorInterface;
use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Products\Category\Entity\CategoryProduct;
use BaksDev\Products\Category\Entity\Info\CategoryProductInfo;
use BaksDev\Products\Category\Entity\Offers\CategoryProductOffers;
use BaksDev\Products\Category\Entity\Offers\Variation\CategoryProductVariation;
use BaksDev\Products\Category\Entity\Offers\Variation\Modification\CategoryProductModification;
use BaksDev\Products\Category\Entity\Trans\CategoryProductTrans;
use BaksDev\Products\Category\Type\Id\CategoryProductUid;
use BaksDev\Products\Product\Entity\Category\ProductCategory;
use BaksDev\Products\Product\Entity\Event\ProductEvent;
use BaksDev\Products\Product\Entity\Info\ProductInfo;
use BaksDev\Products\Product\Entity\Offers\Image\ProductOfferImage;
use BaksDev\Products\Product\Entity\Offers\Price\ProductOfferPrice;
use BaksDev\Products\Product\Entity\Offers\ProductOffer;
use BaksDev\Products\Product\Entity\Offers\Quantity\ProductOfferQuantity;
use BaksDev\Products\Product\Entity\Offers\Variation\Image\ProductVariationImage;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\Image\ProductModificationImage;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\Price\ProductModificationPrice;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\ProductModification;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\Quantity\ProductModificationQuantity;
use BaksDev\Products\Product\Entity\Offers\Variation\Price\ProductVariationPrice;
use BaksDev\Products\Product\Entity\Offers\Variation\ProductVariation;
use BaksDev\Products\Product\Entity\Offers\Variation\Quantity\ProductVariationQuantity;
use BaksDev\Products\Product\Entity\Photo\ProductPhoto;
use BaksDev\Products\Product\Entity\Price\ProductPrice;
use BaksDev\Products\Product\Entity\Product;
use BaksDev\Products\Product\Entity\Property\ProductProperty;
use BaksDev\Products\Product\Entity\Trans\ProductTrans;
use BaksDev\Products\Product\Forms\ProductFilter\Admin\ProductFilterDTO;
use BaksDev\Products\Product\Forms\ProductFilter\Admin\Property\ProductFilterPropertyDTO;
use BaksDev\Products\Sign\Entity\Code\ProductSignCode;
use BaksDev\Products\Sign\Entity\Event\ProductSignEvent;
use BaksDev\Products\Sign\Entity\Invariable\ProductSignInvariable;
use BaksDev\Products\Sign\Entity\Modify\ProductSignModify;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusNew;
use BaksDev\Users\Profile\UserProfile\Entity\Personal\UserProfilePersonal;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Repository\UserProfileTokenStorage\UserProfileTokenStorageInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Type\Id\UserUid;

final class NotExistsProductSignCodeRepository implements NotExistsProductSignCodeRepositoryInterface
{
    private ?SearchDTO $search = null;

    private ?ProductFilterDTO $filter = null;

    public function __construct(
        private readonly DBALQueryBuilder $DBALQueryBuilder,
        private readonly PaginatorInterface $paginator,
        private readonly UserProfileTokenStorageInterface $userProfileTokenStorage
    ) {}

    public function search(SearchDTO $search): self
    {
        $this->search = $search;
        return $this;
    }

    public function filter(ProductFilterDTO $filter): static
    {
        $this->filter = $filter;
        return $this;
    }

    /**
     * Метод возвращает пагинатор продукции, которая имеется в наличии, но отсутствует «Честный знак»
     */
    public function findPaginator(): PaginatorInterface
    {
        $user = $this->userProfileTokenStorage->getUser();

        $profile = $this->userProfileTokenStorage->getProfile();

        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();


        // Product
        $dbal->addSelect('product.id as product_id');
        $dbal->addSelect('product.event as product_event');
        $dbal->from(
            Product::class,
            'product'
        );


        $dbal->leftJoin(
            'product',
            ProductEvent::class,
            'product_event',
            'product_event.id = product.event'
        );

        $dbal
            ->addSelect('product_info.url AS product_url')
            ->leftJoin(
                'product',
                ProductInfo::class,
                'product_info',
                'product_info.product = product.id'
            );


        $dbal
            ->addSelect('product_trans.name as product_name')
            ->join(
                'product',
                ProductTrans::class,
                'product_trans',
                'product_trans.event = product.event AND product_trans.local = :local'
            );


        /**
         * Торговое предложение
         */

        $dbal
            ->addSelect('product_offer.const as product_offer_const')
            ->addSelect('product_offer.value as product_offer_value')
            ->addSelect('product_offer.postfix as product_offer_postfix')
            ->leftJoin(
                'product',
                ProductOffer::class,
                'product_offer',
                'product_offer.event = product.event'
            );


        if($this->filter?->getOffer())
        {
            $dbal->andWhere('product_offer.value = :offer');
            $dbal->setParameter('offer', $this->filter->getOffer());
        }


        // Получаем тип торгового предложения
        $dbal
            ->addSelect('category_offer.reference as product_offer_reference')
            ->leftJoin(
                'product_offer',
                CategoryProductOffers::TABLE,
                'category_offer',
                'category_offer.id = product_offer.category_offer'
            );

        // Множественные варианты торгового предложения

        $dbal->addSelect('product_variation.const as product_variation_const')
            ->addSelect('product_variation.value as product_variation_value')
            ->addSelect('product_variation.postfix as product_variation_postfix')
            ->leftJoin(
                'product_offer',
                ProductVariation::class,
                'product_variation',
                'product_variation.offer = product_offer.id'
            );

        if($this->filter?->getVariation())
        {
            $dbal->andWhere('product_variation.value = :variation');
            $dbal->setParameter('variation', $this->filter->getVariation());
        }

        // Получаем тип множественного варианта
        $dbal
            ->addSelect('category_variation.reference as product_variation_reference')
            ->leftJoin(
                'product_variation',
                CategoryProductVariation::class,
                'category_variation',
                'category_variation.id = product_variation.category_variation'
            );


        // Модификация множественного варианта торгового предложения

        $dbal
            ->addSelect('product_modification.const as product_modification_const')
            ->addSelect('product_modification.value as product_modification_value')
            ->addSelect('product_modification.postfix as product_modification_postfix')
            ->leftJoin(
                'product_variation',
                ProductModification::class,
                'product_modification',
                'product_modification.variation = product_variation.id'
            );

        if($this->filter?->getModification())
        {
            $dbal->andWhere('product_modification.value = :modification');
            $dbal->setParameter('modification', $this->filter->getModification());
        }

        // Получаем тип модификации множественного варианта
        $dbal
            ->addSelect('category_offer_modification.reference as product_modification_reference')
            ->leftJoin(
                'product_modification',
                CategoryProductModification::class,
                'category_offer_modification',
                'category_offer_modification.id = product_modification.category_modification'
            );

        // Артикул продукта
        $dbal->addSelect(
            '
            COALESCE(
                product_modification.article,
                product_variation.article,
                product_offer.article,
                product_info.article
            ) AS product_article'
        );


        // Фото продукта

        $dbal->leftJoin(
            'product_modification',
            ProductModificationImage::class,
            'product_modification_image',
            '
                product_modification_image.modification = product_modification.id AND
                product_modification_image.root = true
			'
        );

        $dbal->leftJoin(
            'product_offer',
            ProductVariationImage::class,
            'product_variation_image',
            '
                product_variation_image.variation = product_variation.id AND
                product_variation_image.root = true
			'
        );

        $dbal->leftJoin(
            'product_offer',
            ProductOfferImage::class,
            'product_offer_images',
            '
                product_variation_image.name IS NULL AND
                product_offer_images.offer = product_offer.id AND
                product_offer_images.root = true
			'
        );

        $dbal->leftJoin(
            'product_offer',
            ProductPhoto::class,
            'product_photo',
            '
                product_offer_images.name IS NULL AND
                product_photo.event = product.event AND
                product_photo.root = true
			'
        );


        $dbal->addSelect(
            "
			CASE

			 WHEN product_modification_image.name IS NOT NULL THEN
					CONCAT ( '/upload/".$dbal->table(ProductModificationImage::class)."' , '/', product_modification_image.name)
			   WHEN product_variation_image.name IS NOT NULL THEN
					CONCAT ( '/upload/".$dbal->table(ProductVariationImage::class)."' , '/', product_variation_image.name)
			   WHEN product_offer_images.name IS NOT NULL THEN
					CONCAT ( '/upload/".$dbal->table(ProductOfferImage::class)."' , '/', product_offer_images.name)
			   WHEN product_photo.name IS NOT NULL THEN
					CONCAT ( '/upload/".$dbal->table(ProductPhoto::class)."' , '/', product_photo.name)
			   ELSE NULL
			END AS product_image
		"
        );


        // Расширение файла
        $dbal->addSelect(
            '
            COALESCE(
                product_modification_image.ext,
                product_variation_image.ext,
                product_offer_images.ext,
                product_photo.ext
            ) AS product_image_ext'
        );


        $dbal->addSelect(
            '
            COALESCE(
                product_modification_image.cdn,
                product_variation_image.cdn,
                product_offer_images.cdn,
                product_photo.cdn
            ) AS product_image_cdn'
        );


        // Категория
        $dbal
            ->addSelect('product_event_category.category AS product_category')
            ->leftJoin(
                'product_event',
                ProductCategory::class,
                'product_event_category',
                'product_event_category.event = product_event.id AND product_event_category.root = true'
            );

        if($this->filter?->getCategory())
        {
            $dbal->andWhere('product_event_category.category = :category');
            $dbal->setParameter('category', $this->filter->getCategory(), CategoryProductUid::TYPE);
        }

        $dbal->leftJoin(
            'product_event_category',
            CategoryProduct::TABLE,
            'category',
            'category.id = product_event_category.category'
        );

        $dbal
            ->addSelect('category_info.url AS category_url')
            ->leftJoin(
                'category',
                CategoryProductInfo::TABLE,
                'category_info',
                'category_info.event = category.event'
            );

        $dbal
            ->addSelect('category_trans.name AS category_name')
            ->leftJoin(
                'category',
                CategoryProductTrans::TABLE,
                'category_trans',
                'category_trans.event = category.event AND category_trans.local = :local'
            );


        /* Наличие продукта */

        $dbal->leftJoin(
            'product',
            ProductPrice::class,
            'product_price',
            'product_price.event = product.event'
        );

        /* Наличие и резерв торгового предложения */
        $dbal->leftJoin(
            'product_offer',
            ProductOfferQuantity::class,
            'product_offer_quantity',
            'product_offer_quantity.offer = product_offer.id'
        );

        /* Наличие и резерв множественного варианта */
        $dbal->leftJoin(
            'product_variation',
            ProductVariationQuantity::class,
            'product_variation_quantity',
            'product_variation_quantity.variation = product_variation.id'
        );

        $dbal
            ->leftJoin(
                'product_modification',
                ProductModificationQuantity::class,
                'product_modification_quantity',
                'product_modification_quantity.modification = product_modification.id'
            );


                $dbal->addSelect("
        			COALESCE(
                        NULLIF(product_modification_quantity.quantity, 0),
                        NULLIF(product_variation_quantity.quantity, 0),
                        NULLIF(product_offer_quantity.quantity, 0),
                        NULLIF(product_price.quantity, 0),
                        0
                    ) AS product_quantity
        		");


        $dbal->addSelect("
			COALESCE(
                NULLIF(product_modification_quantity.reserve, 0),
                NULLIF(product_variation_quantity.reserve, 0),
                NULLIF(product_offer_quantity.reserve, 0),
                NULLIF(product_price.reserve, 0),
                0
            ) AS product_reserve
		");


        /* Цена торгового предо жения */
//        $dbal->leftJoin(
//            'product_offer',
//            ProductOfferPrice::class,
//            'product_offer_price',
//            'product_offer_price.offer = product_offer.id'
//        );
//
//        /* Цена множественного варианта */
//        $dbal->leftJoin(
//            'product_variation',
//            ProductVariationPrice::class,
//            'product_variation_price',
//            'product_variation_price.variation = product_variation.id'
//        );
//
//        /* Цена модификации множественного варианта */
//        $dbal->leftJoin(
//            'product_modification',
//            ProductModificationPrice::class,
//            'product_modification_price',
//            'product_modification_price.modification = product_modification.id'
//        );





        /* Стоимость продукта */

//        $dbal->addSelect(
//            '
//			COALESCE(
//                NULLIF(product_modification_price.price, 0),
//                NULLIF(product_variation_price.price, 0),
//                NULLIF(product_offer_price.price, 0),
//                NULLIF(product_price.price, 0)
//            ) AS product_price
//		'
//        );

        /* Валюта продукта */

//        $dbal->addSelect(
//            '
//			CASE
//			   WHEN product_modification_price.price IS NOT NULL AND product_modification_price.price > 0
//			   THEN product_modification_price.currency
//
//			   WHEN product_variation_price.price IS NOT NULL AND product_variation_price.price > 0
//			   THEN product_variation_price.currency
//
//			   WHEN product_offer_price.price IS NOT NULL AND product_offer_price.price > 0
//			   THEN product_offer_price.currency
//
//			   WHEN product_price.price IS NOT NULL AND product_price.price > 0
//			   THEN product_price.currency
//
//			   ELSE NULL
//			END AS product_currency
//		'
//        );


        $notExists = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        $notExists
            ->select('1')
            ->from(ProductSignInvariable::class, 'invariable')
            ->andWhere('invariable.usr = :usr')
            ->andWhere('invariable.product = product.id')
            ->andWhere('invariable.offer = product_offer.const')
            ->andWhere('invariable.variation = product_variation.const')
            ->andWhere('invariable.modification = product_modification.const');

        $dbal->setParameter('usr', $user, UserUid::TYPE);

        $notExists
            ->join(
                'invariable',
                ProductSignEvent::class,
                'event',
                'event.id = invariable.event AND event.status = :status'
            );

        $dbal
            ->setParameter(
                'status',
                ProductSignStatusNew::class,
                ProductSignStatus::TYPE
            );


        $dbal->andWhere('COALESCE(
                NULLIF(product_modification_quantity.quantity, 0),
                NULLIF(product_variation_quantity.quantity, 0),
                NULLIF(product_offer_quantity.quantity, 0),
                NULLIF(product_price.quantity, 0),
                0
            ) != 0');

        $dbal->andWhere('NOT EXISTS ('.$notExists->getSQL().')');


        /** Ответственное лицо */

        //        $dbal
        //            ->leftJoin(
        //                'event',
        //                UserProfile::TABLE,
        //                'users_profile',
        //                'users_profile.id = event.profile'
        //            );
        //
        //
        //        $dbal
        //            ->addSelect('users_profile_personal.username AS users_profile_username')
        //            ->addSelect('users_profile_personal.location AS users_profile_location')
        //            ->leftJoin(
        //                'users_profile',
        //                UserProfilePersonal::TABLE,
        //                'users_profile_personal',
        //                'users_profile_personal.event = users_profile.event'
        //            );


        /**
         * Фильтр по свойства продукта
         */
        if($this->filter->getProperty())
        {
            /** @var ProductFilterPropertyDTO $property */
            foreach($this->filter->getProperty() as $property)
            {
                if($property->getValue())
                {
                    $dbal->join(
                        'product',
                        ProductProperty::class,
                        'product_property_'.$property->getType(),
                        'product_property_'.$property->getType().'.event = product.event AND 
                        product_property_'.$property->getType().'.field = :'.$property->getType().'_const AND 
                        product_property_'.$property->getType().'.value = :'.$property->getType().'_value'
                    );

                    $dbal->setParameter($property->getType().'_const', $property->getConst());
                    $dbal->setParameter($property->getType().'_value', $property->getValue());
                }
            }
        }

        /* Поиск */
        if($this->search?->getQuery())
        {
            $dbal
                ->createSearchQueryBuilder($this->search)
                //->addSearchLike('code.code')
                //->addSearchLike('orders.number')pro
                ->addSearchLike('product_modification.article')
                ->addSearchLike('product_variation.article')
                ->addSearchLike('product_offer.article')
                ->addSearchLike('product_info.article');
        }

        //$dbal->orderBy('modify.mod_date', 'DESC');


        //dd($dbal->fetchAllAssociative());

        return $this->paginator->fetchAllAssociative($dbal);
    }
}
