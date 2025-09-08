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

namespace BaksDev\Products\Sign\Repository\AllProductSignExport;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Field\Pack\Inn\Type\InnField;
use BaksDev\Field\Pack\Kpp\Type\KppField;
use BaksDev\Field\Pack\Okpo\Type\OkpoField;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Entity\Invariable\OrderInvariable;
use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Orders\Order\Entity\Products\OrderProduct;
use BaksDev\Orders\Order\Entity\Products\Price\OrderPrice;
use BaksDev\Orders\Order\Entity\User\Delivery\OrderDelivery;
use BaksDev\Orders\Order\Entity\User\OrderUser;
use BaksDev\Products\Product\Entity\Offers\ProductOffer;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\ProductModification;
use BaksDev\Products\Product\Entity\Offers\Variation\ProductVariation;
use BaksDev\Products\Sign\Entity\Code\ProductSignCode;
use BaksDev\Products\Sign\Entity\Event\ProductSignEvent;
use BaksDev\Products\Sign\Entity\Invariable\ProductSignInvariable;
use BaksDev\Products\Sign\Entity\Modify\ProductSignModify;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusDone;
use BaksDev\Users\Profile\TypeProfile\Entity\Section\Fields\TypeProfileSectionField;
use BaksDev\Users\Profile\TypeProfile\Type\Id\Choice\TypeProfileIndividual;
use BaksDev\Users\Profile\TypeProfile\Type\Id\Choice\TypeProfileOrganization;
use BaksDev\Users\Profile\TypeProfile\Type\Id\Choice\TypeProfileUser;
use BaksDev\Users\Profile\UserProfile\Entity\Event\UserProfileEvent;
use BaksDev\Users\Profile\UserProfile\Entity\Event\Value\UserProfileValue;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Types\Types;
use Generator;
use InvalidArgumentException;

final class AllProductSignExportRepository implements AllProductSignExportInterface
{
    private UserProfileUid|false $profile = false;

    private DateTimeImmutable|false $from = false;

    private DateTimeImmutable|false $to = false;

    private array|false $type = false;

    public function __construct(private readonly DBALQueryBuilder $DBALQueryBuilder) {}

    public function forProfile(UserProfile|UserProfileUid|string $profile): self
    {
        if($profile instanceof UserProfile)
        {
            $profile = $profile->getId();
        }

        if(is_string($profile))
        {
            $profile = new UserProfileUid($profile);
        }

        $this->profile = $profile;

        return $this;
    }

    public function dateFrom(DateTimeImmutable|string $from): self
    {
        if(is_string($from))
        {
            $from = new DateTimeImmutable($from);
        }

        $this->from = $from;

        return $this;

    }

    public function dateTo(DateTimeImmutable|string $to): self
    {
        if(is_string($to))
        {
            $to = new DateTimeImmutable($to);
        }

        $this->to = $to;

        return $this;
    }

    /** Честные знаки для передачи */
    public function onlyTransfer(): self
    {
        $this->type = [TypeProfileOrganization::TYPE, TypeProfileIndividual::TYPE];

        return $this;
    }

    /**
     * Честные знаки для вывода из оборота (покупатель)
     */
    public function onlyDoneBuyer(): self
    {
        $this->type = [TypeProfileUser::TYPE];

        return $this;
    }


    public function findAll(): Generator|false
    {
        if(false === ($this->profile instanceof UserProfileUid))
        {
            throw new InvalidArgumentException('Invalid Argument UserProfile');
        }

        if(false === ($this->from instanceof DateTimeImmutable))
        {
            throw new InvalidArgumentException('Invalid Argument from DateTime');
        }

        if(false === ($this->to instanceof DateTimeImmutable))
        {
            throw new InvalidArgumentException('Invalid Argument to DateTime');
        }

        if(empty($this->type))
        {
            throw new InvalidArgumentException('Invalid Argument Type');
        }

        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        $dbal
            ->from(ProductSignEvent::class, 'sign_event')
            ->andWhere('sign_event.status = :sign_status AND sign_event.ord IS NOT NULL')
            ->setParameter(
                key: 'sign_status',
                value: ProductSignStatusDone::class,
                type: ProductSignStatus::TYPE,
            );

        $dbal
            ->join(
                'sign_event',
                ProductSignModify::class,
                'sign_modify',
                '
                    sign_modify.event = sign_event.id 
                    AND sign_modify.mod_date BETWEEN :from AND :to
                ',
            )
            ->setParameter(
                key: 'from',
                value: $this->from,
                type: Types::DATETIME_IMMUTABLE,
            )
            ->setParameter(
                key: 'to',
                value: $this->to,
                type: Types::DATETIME_IMMUTABLE,
            );

        $dbal
            ->leftJoin(
                'sign_event',
                ProductSignInvariable::class,
                'sign_invariable',
                'sign_invariable.main = sign_event.main',
            );

        $dbal
            ->leftJoin(
                'sign_event',
                ProductSignCode::class,
                'sign_code',
                'sign_code.main = sign_event.main',
            );

        $dbal
            ->join(
                'sign_event',
                Order::class,
                'main',
                'main.id = sign_event.ord',
            );

        $dbal
            ->addSelect('order_invariable.number')
            ->join(
                'main',
                OrderInvariable::class,
                'order_invariable',
                'order_invariable.main = main.id 
                AND order_invariable.profile = :profile',
            )
            ->setParameter(
                key: 'profile',
                value: $this->profile,
                type: UserProfileUid::TYPE,
            );

        $dbal
            ->leftJoin(
                'main',
                OrderEvent::class,
                'event',
                'event.id = main.event',
            );

        $dbal
            ->addSelect('order_delivery.delivery_date AS delivery_date')
            ->leftJoin(
                'order_user',
                OrderDelivery::class,
                'order_delivery',
                'order_delivery.usr = order_user.id',
            );

        $dbal
            ->leftJoin(
                'main',
                OrderUser::class,
                'order_user',
                'order_user.event = main.event',
            );


        $dbal
            ->leftJoin(
                'event',
                OrderProduct::class,
                'order_product',
                'order_product.event = event.id',
            );

        $dbal
            ->leftJoin(
                'event',
                OrderPrice::class,
                'order_price',
                'order_price.product = order_product.id',
            );

        $dbal
            ->leftJoin(
                'order_product',
                ProductOffer::class,
                'product_offer',
                'product_offer.id = order_product.offer AND product_offer.const = sign_invariable.offer',
            );

        $dbal
            ->leftJoin(
                'order_product',
                ProductVariation::class,
                'product_variation',
                'product_variation.id = order_product.variation AND product_variation.const = sign_invariable.variation',
            );

        $dbal
            ->leftJoin(
                'order_product',
                ProductModification::class,
                'product_modification',
                'product_modification.id = order_product.modification AND product_modification.const = sign_invariable.modification',
            );

        $dbal
            ->join(
                'order_user',
                UserProfileEvent::class,
                'user_profile_event',
                'user_profile_event.id = order_user.profile AND user_profile_event.type IN (:user_profile_type)',
            )
            ->setParameter(
                key: 'user_profile_type',
                value: $this->type,
                type: ArrayParameterType::STRING,
            );

        $dbal
            ->leftJoin(
                'order_user',
                UserProfileValue::class,
                'user_profile_value',
                'user_profile_value.event = order_user.profile',
            );

        /** Выбираем только контакт и номер телефон */
        $dbal
            ->join(
                'user_profile_value',
                TypeProfileSectionField::class,
                'type_section_field_client',
                '
                type_section_field_client.id = user_profile_value.field AND
                type_section_field_client.type IN (:fields)
            ')
            ->setParameter(
                key: 'fields',
                value: [InnField::TYPE, KppField::TYPE, OkpoField::TYPE],
                type: ArrayParameterType::STRING,
            );


        $dbal->addSelect(
            "JSON_AGG
                    ( DISTINCT
        
                            JSONB_BUILD_OBJECT
                            (
                                'article', COALESCE(
                                    product_modification.article,
                                    product_variation.article,
                                    product_offer.article
                                ),
                                'price', order_price.price,
                                'total', order_price.total,
                                'code', sign_code.code
                            )
        
                    ) FILTER (WHERE COALESCE(
                                    product_modification.article,
                                    product_variation.article,
                                    product_offer.article
                                ) IS NOT NULL) AS products",
        );


        $dbal->addSelect(
            "JSON_AGG
                    ( DISTINCT
        
                        JSONB_BUILD_OBJECT
                        (
                            'type', type_section_field_client.type,
                            'value', user_profile_value.value
                        )
        
                    ) AS requisite",
        );


        $dbal->allGroupByExclude();

        return $dbal->fetchAllHydrate(ProductSignExportResult::class);
    }
}
