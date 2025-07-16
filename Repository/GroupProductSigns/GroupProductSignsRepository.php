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

namespace BaksDev\Products\Sign\Repository\GroupProductSigns;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Services\Paginator\PaginatorInterface;
use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Products\Category\Entity\CategoryProduct;
use BaksDev\Products\Category\Entity\Info\CategoryProductInfo;
use BaksDev\Products\Category\Entity\Offers\CategoryProductOffers;
use BaksDev\Products\Category\Entity\Offers\Variation\CategoryProductVariation;
use BaksDev\Products\Category\Entity\Offers\Variation\Modification\CategoryProductModification;
use BaksDev\Products\Category\Type\Id\CategoryProductUid;
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
use BaksDev\Products\Product\Entity\Property\ProductProperty;
use BaksDev\Products\Product\Entity\Trans\ProductTrans;
use BaksDev\Products\Product\Forms\ProductFilter\Admin\ProductFilterDTO;
use BaksDev\Products\Product\Forms\ProductFilter\Admin\Property\ProductFilterPropertyDTO;
use BaksDev\Products\Sign\Entity\Code\ProductSignCode;
use BaksDev\Products\Sign\Entity\Event\ProductSignEvent;
use BaksDev\Products\Sign\Entity\Invariable\ProductSignInvariable;
use BaksDev\Products\Sign\Entity\Modify\ProductSignModify;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Forms\ProductSignFilter\ProductSignFilterDTO;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus;
use BaksDev\Users\Profile\UserProfile\Entity\Event\Personal\UserProfilePersonal;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Repository\UserProfileTokenStorage\UserProfileTokenStorageInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Type\Id\UserUid;
use Doctrine\DBAL\Types\Types;

final class GroupProductSignsRepository implements GroupProductSignsInterface
{
    private ?SearchDTO $search = null;

    private ?ProductFilterDTO $filter = null;

    private ?ProductSignFilterDTO $status = null;

    public function __construct(
        private readonly DBALQueryBuilder $DBALQueryBuilder,
        private readonly PaginatorInterface $paginator,
        private readonly UserProfileTokenStorageInterface $userProfileTokenStorage,
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

    public function status(ProductSignFilterDTO $status): static
    {
        $this->status = $status;
        return $this;
    }

    /** Метод возвращает пагинатор ProductSign */
    public function findPaginator(): PaginatorInterface
    {
        $user = $this->userProfileTokenStorage->getUser();

        $profile = $this->userProfileTokenStorage->getProfile();

        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        $dbal->addSelect("JSON_AGG( DISTINCT JSONB_BUILD_OBJECT (  'number', invariable.number  ) ) AS sign_number");

        $dbal
            ->addSelect('COUNT(*) AS counter')
            ->addSelect('invariable.part AS sign_part')
            //->addSelect('invariable.event AS sign_event')
            //->addSelect('invariable.number AS sign_number')
            ->from(
                ProductSignInvariable::class,
                'invariable'
            )
            ->andWhere('invariable.usr = :usr')
            ->setParameter('usr', $user, UserUid::TYPE);


        if($this->filter->getAll() === false)
        {
            //            $dbal
            //                ->andWhere('(invariable.profile = :profile OR invariable.seller = :profile)')
            //                ->setParameter('profile', $profile, UserProfileUid::TYPE);
        }

        $dbal
            ->leftJoin(
                'invariable',
                ProductSignCode::class,
                'code',
                'code.main = invariable.main'
            );


        $dbal
            //->addSelect('main.id AS sign_id')
            //->addSelect('main.event AS sign_event')
            ->join(
                'invariable',
                ProductSign::class,
                'main',
                'main.id = invariable.main'
            );


        $dbal
            ->addSelect('event.ord AS order_id')
            ->addSelect('event.status AS sign_status')
            ->addSelect('event.comment AS sign_comment')
            ->leftJoin(
                'main',
                ProductSignEvent::class,
                'event',
                'event.id = main.event'
            );

        if($this->status?->getStatus())
        {
            $dbal
                ->andWhere('event.status = :status')
                ->setParameter('status', $this->status->getStatus(), ProductSignStatus::TYPE);
        }


        if($this->filter->getAll() === false)
        {
            $dbal->andWhere('(event.profile IS NULL OR event.profile = :profile)')
                ->setParameter('profile', $profile, UserProfileUid::TYPE);
        }


        $dbal
            ->addSelect("DATE(modify.mod_date) AS mod_date")
            ->leftJoin(
                'main',
                ProductSignModify::class,
                'modify',
                'modify.event = main.event'
            );


        if($this->status?->getFrom() && $this->status?->getTo())
        {
            $dbal
                ->andWhere('DATE(modify.mod_date) BETWEEN :date_from AND :date_to')
                ->setParameter('date_from', $this->status->getFrom(), Types::DATE_IMMUTABLE)
                ->setParameter('date_to', $this->status->getTo(), Types::DATE_IMMUTABLE);
        }
        else
        {
            if($this->status?->getFrom())
            {
                $dbal
                    ->andWhere('DATE(modify.mod_date) >= :date_from')
                    ->setParameter('date_from', $this->status->getFrom(), Types::DATE_IMMUTABLE);
            }

            if($this->status?->getTo())
            {
                $dbal
                    ->andWhere('DATE(modify.mod_date) <= :date_to')
                    ->setParameter('date_to', $this->status->getTo(), Types::DATE_IMMUTABLE);
            }
        }


        $dbal
            ->addSelect('orders.number AS order_number')
            ->leftJoin(
                'event',
                Order::class,
                'orders',
                'orders.id = event.ord'
            );


        // Product
        $dbal->addSelect('product.id as product_id'); //->addGroupBy('product.id');
        $dbal->addSelect('product.event as product_event'); //->addGroupBy('product.event');
        $dbal->join(
            'invariable',
            Product::class,
            'product',
            'product.id = invariable.product'
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
            ->addSelect('product_offer.id as product_offer_uid')
            ->addSelect('product_offer.value as product_offer_value')
            ->addSelect('product_offer.postfix as product_offer_postfix')
            ->leftJoin(
                'product',
                ProductOffer::class,
                'product_offer',
                'product_offer.event = product.event AND product_offer.const = invariable.offer'
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
                CategoryProductOffers::class,
                'category_offer',
                'category_offer.id = product_offer.category_offer'
            );

        // Множественные варианты торгового предложения

        $dbal
            ->addSelect('product_variation.const as product_variation_const')
            ->addSelect('product_variation.id as product_variation_uid')
            ->addSelect('product_variation.value as product_variation_value')
            ->addSelect('product_variation.postfix as product_variation_postfix')
            ->leftJoin(
                'product_offer',
                ProductVariation::class,
                'product_variation',
                'product_variation.offer = product_offer.id AND product_variation.const = invariable.variation'
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
            ->addSelect('product_modification.id as product_modification_uid')
            ->addSelect('product_modification.value as product_modification_value')
            ->addSelect('product_modification.postfix as product_modification_postfix')
            ->leftJoin(
                'product_variation',
                ProductModification::class,
                'product_modification',
                'product_modification.variation = product_variation.id AND product_modification.const = invariable.modification'
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
            CategoryProduct::class,
            'category',
            'category.id = product_event_category.category'
        );

        $dbal
            ->addSelect('category_info.url AS category_url')
            ->leftJoin(
                'category',
                CategoryProductInfo::class,
                'category_info',
                'category_info.event = category.event'
            );


        /**
         * Владелец
         */

        $dbal
            ->leftJoin(
                'invariable',
                UserProfile::class,
                'users_profile',
                'users_profile.id = invariable.profile',
            );


        $dbal
            ->addSelect('users_profile_personal.username AS users_profile_username')
            ->leftJoin(
                'users_profile',
                UserProfilePersonal::class,
                'users_profile_personal',
                'users_profile_personal.event = users_profile.event'
            );


        /**
         * Продавец
         */

        $dbal
            ->leftJoin(
                'invariable',
                UserProfile::class,
                'users_profile_seller',
                'users_profile_seller.id = invariable.seller',
            );


        $dbal
            ->addSelect('users_profile_personal_seller.username AS seller_username')
            ->leftJoin(
                'users_profile_seller',
                UserProfilePersonal::class,
                'users_profile_personal_seller',
                'users_profile_personal_seller.event = users_profile_seller.event',
            );



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

        $dbal->orderBy('DATE(modify.mod_date)', 'DESC');
        $dbal->addOrderBy('invariable.part', 'DESC');

        //$dbal->orderBy('invariable.part', 'DESC');

        /* Поиск */
        if($this->search?->getQuery())
        {

            if(str_starts_with($this->search->getQuery(), '(00)'))
            {
                $dbal
                    ->createSearchQueryBuilder($this->search)
                    ->addSearchLike('invariable.part');
            }

            elseif(str_starts_with($this->search->getQuery(), '(01)') || str_starts_with($this->search->getQuery(), '01'))
            {
                $dbal
                    ->createSearchQueryBuilder($this->search)
                    ->addSearchLike('code.code');
            }

            elseif(preg_match('/^\d{3}\.\d{3}\.\d{3}\.\d{3}$/', $this->search->getQuery()))
            {

                $dbal
                    ->createSearchQueryBuilder($this->search)
                    ->addSearchLike('orders.number');
            }

            else
            {
                $dbal
                    ->createSearchQueryBuilder($this->search)
                    ->addSearchLike('code.code')
                    ->addSearchLike('product_modification.article')
                    ->addSearchLike('product_variation.article')
                    ->addSearchLike('product_offer.article')
                    ->addSearchLike('product_info.article');
            }


            $dbal
                ->addOrderBy('product_modification.id')
                ->addOrderBy('product_variation.id')
                ->addOrderBy('product_offer.id')
                ->addOrderBy('product.id')
                ->addOrderBy('counter', 'DESC');
        }

        $dbal->allGroupByExclude();

        return $this->paginator->fetchAllAssociative($dbal);
    }
}
