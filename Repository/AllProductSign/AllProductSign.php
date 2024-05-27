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

namespace BaksDev\Products\Sign\Repository\AllProductSign;


use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Services\Paginator\PaginatorInterface;
use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Category\Entity\Info\ProductCategoryInfo;
use BaksDev\Products\Category\Entity\Offers\ProductCategoryOffers;
use BaksDev\Products\Category\Entity\Offers\Variation\Modification\ProductCategoryModification;
use BaksDev\Products\Category\Entity\Offers\Variation\ProductCategoryVariation;
use BaksDev\Products\Category\Entity\Trans\ProductCategoryTrans;
use BaksDev\Products\Category\Type\Id\ProductCategoryUid;
use BaksDev\Products\Product\Entity\Category\ProductCategory;
use BaksDev\Products\Product\Entity\Event\ProductEvent;
use BaksDev\Products\Product\Entity\Info\ProductInfo;
use BaksDev\Products\Product\Entity\Offers\Image\ProductOfferImage;
use BaksDev\Products\Product\Entity\Offers\ProductOffer;
use BaksDev\Products\Product\Entity\Offers\Variation\Image\ProductVariationImage;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\Image\ProductModificationImage;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\ProductModification;
use BaksDev\Products\Product\Entity\Offers\Variation\ProductVariation;
use BaksDev\Products\Product\Entity\Photo\ProductPhoto;
use BaksDev\Products\Product\Entity\Product;
use BaksDev\Products\Product\Entity\Trans\ProductTrans;
use BaksDev\Products\Sign\Entity\Code\ProductSignCode;
use BaksDev\Products\Sign\Entity\Event\ProductSignEvent;
use BaksDev\Products\Sign\Entity\Modify\ProductSignModify;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Users\Profile\UserProfile\Entity\Personal\UserProfilePersonal;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\User\Type\Id\UserUid;

final class AllProductSign implements AllProductSignInterface
{
    private PaginatorInterface $paginator;

    private DBALQueryBuilder $DBALQueryBuilder;

    private ?SearchDTO $search = null;

    public function __construct(
        DBALQueryBuilder $DBALQueryBuilder,
        PaginatorInterface $paginator,
    )
    {
        $this->paginator = $paginator;
        $this->DBALQueryBuilder = $DBALQueryBuilder;
    }

    public function search(SearchDTO $search): self
    {
        $this->search = $search;
        return $this;
    }

    /** Метод возвращает пагинатор ProductSign */
    public function fetchAllProductSignAssociative(UserUid $user): PaginatorInterface
    {
        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        $dbal
            ->addSelect('code.code AS sign_code')
            ->from(
                ProductSignCode::class, 'code'
            )
            ->andWhere('code.usr = :usr')
            ->setParameter('usr', $user, UserUid::TYPE);


        $dbal
            ->addSelect('main.id AS sign_id')
            ->addSelect('main.event AS sign_event')
            ->join(
                'code',
                ProductSign::class,
                'main',
                'main.event = code.event'
            );

        $dbal
            ->addSelect('event.status AS sign_status')
            ->addSelect('event.comment AS sign_comment')
            ->leftJoin(
                'code',
                ProductSignEvent::class,
                'event',
                'event.id = code.event'
            );

        $dbal
            ->addSelect('modify.mod_date AS sign_date')

            ->leftJoin(
                'code',
                ProductSignModify::class,
                'modify',
                'modify.event = code.event'
            );

        // Product
        $dbal->addSelect('product.id as product_id'); //->addGroupBy('product.id');
        $dbal->addSelect('product.event as product_event'); //->addGroupBy('product.event');
        $dbal->join(
            'code',
            Product::class,
            'product',
            'product.id = code.product'
        );


        $dbal->join(
            'product',
            ProductEvent::class,
            'product_event',
            'product_event.id = product.event'
        );

        $dbal
            ->addSelect('product_info.url AS product_url')
            ->leftJoin(
                'product_event',
                ProductInfo::class,
                'product_info',
                'product_info.product = product.id'
            );


        $dbal
            ->addSelect('product_trans.name as product_name')
            ->join(
                'product_event',
                ProductTrans::class,
                'product_trans',
                'product_trans.event = product_event.id AND product_trans.local = :local'
            );


        /**
         * Торговое предложение
         */

        $dbal
            ->addSelect('product_offer.id as product_offer_uid')
            ->addSelect('product_offer.value as product_offer_value')
            ->addSelect('product_offer.postfix as product_offer_postfix')
            ->leftJoin(
                'product_event',
                ProductOffer::class,
                'product_offer',
                'product_offer.event = product_event.id AND product_offer.const = code.offer'
            );

        //        if($this->filter?->getOffer())
        //        {
        //            $dbal->andWhere('product_offer.value = :offer');
        //            $dbal->setParameter('offer', $this->filter->getOffer());
        //        }


        // Получаем тип торгового предложения
        $dbal
            ->addSelect('category_offer.reference as product_offer_reference')
            ->leftJoin(
                'product_offer',
                ProductCategoryOffers::TABLE,
                'category_offer',
                'category_offer.id = product_offer.category_offer'
            );

        // Множественные варианты торгового предложения

        $dbal->addSelect('product_variation.id as product_variation_uid')
            ->addSelect('product_variation.value as product_variation_value')
            ->addSelect('product_variation.postfix as product_variation_postfix')
            ->leftJoin(
                'product_offer',
                ProductVariation::class,
                'product_variation',
                'product_variation.offer = product_offer.id AND product_variation.const = code.variation'
            );

        //        if($this->filter?->getVariation())
        //        {
        //            $dbal->andWhere('product_variation.value = :variation');
        //            $dbal->setParameter('variation', $this->filter->getVariation());
        //        }

        // Получаем тип множественного варианта
        $dbal
            ->addSelect('category_variation.reference as product_variation_reference')
            ->leftJoin(
                'product_variation',
                ProductCategoryVariation::class,
                'category_variation',
                'category_variation.id = product_variation.category_variation'
            );

        // Модификация множественного варианта торгового предложения

        $dbal
            ->addSelect('product_modification.id as product_modification_uid')
            ->addSelect('product_modification.value as product_modification_value')
            ->addSelect('product_modification.postfix as product_modification_postfix')
            ->leftJoin(
                'product_variation',
                ProductModification::class,
                'product_modification',
                'product_modification.variation = product_variation.id AND product_modification.const = code.modification'
            );

        //        if($this->filter?->getModification())
        //        {
        //            $dbal->andWhere('product_modification.value = :modification');
        //            $dbal->setParameter('modification', $this->filter->getModification());
        //        }

        // Получаем тип модификации множественного варианта
        $dbal
            ->addSelect('category_offer_modification.reference as product_modification_reference')
            ->leftJoin(
                'product_modification',
                ProductCategoryModification::class,
                'category_offer_modification',
                'category_offer_modification.id = product_modification.category_modification'
            );

        // Артикул продукта

        $dbal->addSelect(
            '
			CASE
			   WHEN product_modification.article IS NOT NULL THEN product_modification.article
			   WHEN product_variation.article IS NOT NULL THEN product_variation.article
			   WHEN product_offer.article IS NOT NULL THEN product_offer.article
			   WHEN product_info.article IS NOT NULL THEN product_info.article
			   ELSE NULL
			END AS product_article
		'
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
                product_photo.event = product_event.id AND
                product_photo.root = true
			'
        );

        $dbal->addSelect(
            "
			CASE
			 
			 WHEN product_modification_image.name IS NOT NULL THEN
					CONCAT ( '/upload/".ProductModificationImage::TABLE."' , '/', product_modification_image.name)
			   WHEN product_variation_image.name IS NOT NULL THEN
					CONCAT ( '/upload/".ProductVariationImage::TABLE."' , '/', product_variation_image.name)
			   WHEN product_offer_images.name IS NOT NULL THEN
					CONCAT ( '/upload/".ProductOfferImage::TABLE."' , '/', product_offer_images.name)
			   WHEN product_photo.name IS NOT NULL THEN
					CONCAT ( '/upload/".ProductPhoto::TABLE."' , '/', product_photo.name)
			   ELSE NULL
			END AS product_image
		"
        );

        // Расширение файла
        $dbal->addSelect(
            "
			CASE
			
			   WHEN product_modification_image.name IS NOT NULL THEN  product_modification_image.ext
			   WHEN product_variation_image.name IS NOT NULL THEN product_variation_image.ext
			   WHEN product_offer_images.name IS NOT NULL THEN product_offer_images.ext
			   WHEN product_photo.name IS NOT NULL THEN product_photo.ext
			   ELSE NULL
			   
			END AS product_image_ext
		"
        );

        // Флаг загрузки файла CDN
        $dbal->addSelect(
            '
			CASE
			   WHEN product_variation_image.name IS NOT NULL THEN
					product_variation_image.cdn
			   WHEN product_offer_images.name IS NOT NULL THEN
					product_offer_images.cdn
			   WHEN product_photo.name IS NOT NULL THEN
					product_photo.cdn
			   ELSE NULL
			END AS product_image_cdn
		'
        );

        // Категория
        $dbal->leftJoin(
            'product_event',
            ProductCategory::class,
            'product_event_category',
            'product_event_category.event = product_event.id AND product_event_category.root = true'
        );

        //        if($this->filter?->getCategory())
        //        {
        //            $dbal->andWhere('product_event_category.category = :category');
        //            $dbal->setParameter('category', $this->filter->getCategory(), ProductCategoryUid::TYPE);
        //        }

        $dbal->leftJoin(
            'product_event_category',
            \BaksDev\Products\Category\Entity\ProductCategory::TABLE,
            'category',
            'category.id = product_event_category.category'
        );

        $dbal
            ->addSelect('category_info.url AS category_url')
            ->leftJoin(
                'category',
                ProductCategoryInfo::TABLE,
                'category_info',
                'category_info.event = category.event'
            );

        $dbal
            ->addSelect('category_trans.name AS category_name')
            ->leftJoin(
                'category',
                ProductCategoryTrans::TABLE,
                'category_trans',
                'category_trans.event = category.event AND category_trans.local = :local'
            );


        /** Ответственное лицо */

        $dbal
            ->join(
                'event',
                UserProfile::TABLE,
                'users_profile',
                'users_profile.id = event.profile'
            );


        $dbal
            ->addSelect('users_profile_personal.username AS users_profile_username')
            ->addSelect('users_profile_personal.location AS users_profile_location')
            ->join(
                'users_profile',
                UserProfilePersonal::TABLE,
                'users_profile_personal',
                'users_profile_personal.event = users_profile.event'
            );

        /* Поиск */
        if($this->search?->getQuery())
        {
            $dbal
                ->createSearchQueryBuilder($this->search)
                //->addSearchEqualUid('account.id')

                ->addSearchLike('code.code')
            ;
        }

        $dbal->orderBy('modify.mod_date', 'DESC');


        //dd(current($dbal->fetchAllAssociative()));

        return $this->paginator->fetchAllAssociative($dbal);
    }


}
