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

namespace BaksDev\Products\Sign\UseCase\Admin\Pdf;

use BaksDev\Products\Category\Repository\CategoryChoice\CategoryChoiceInterface;
use BaksDev\Products\Category\Type\Id\CategoryProductUid;
use BaksDev\Products\Product\Repository\ProductChoice\ProductChoiceInterface;
use BaksDev\Products\Product\Repository\ProductModificationChoice\ProductModificationChoiceInterface;
use BaksDev\Products\Product\Repository\ProductOfferChoice\ProductOfferChoiceInterface;
use BaksDev\Products\Product\Repository\ProductVariationChoice\ProductVariationChoiceInterface;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Users\Profile\UserProfile\Repository\UserProfileChoice\UserProfileChoiceInterface;
use BaksDev\Users\Profile\UserProfile\Repository\UserProfileTokenStorage\UserProfileTokenStorageInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ProductSignPdfForm extends AbstractType
{
    public function __construct(
        #[AutowireIterator('baks.reference.choice')] private readonly iterable $reference,
        private readonly CategoryChoiceInterface $categoryChoice,
        private readonly ProductChoiceInterface $productChoice,
        private readonly ProductOfferChoiceInterface $productOfferChoice,
        private readonly ProductVariationChoiceInterface $productVariationChoice,
        private readonly ProductModificationChoiceInterface $modificationChoice,
        private readonly UserProfileChoiceInterface $userProfileChoice,
        private readonly UserProfileTokenStorageInterface $userProfileTokenStorage,
    ) {}


    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('number', TextType::class);

        $builder->add('share', CheckboxType::class, ['required' => false]);

        $builder->add('category', ChoiceType::class, [
            'choices' => $this->categoryChoice->findAll(),
            'choice_value' => function(?CategoryProductUid $category) {
                return $category?->getValue();
            },
            'choice_label' => function(CategoryProductUid $category) {
                return (is_int($category->getAttr()) ? str_repeat(' - ', $category->getAttr() - 1) : '').$category->getOptions();
            },
            'label' => false,
            'required' => false,
        ]);

        $builder->add(
            'product',
            HiddenType::class
        );

        /*$builder
            ->add('product', ChoiceType::class, [
                'choices' => $this->productChoice->fetchAllProduct(),
                'choice_value' => function (?ProductUid $product) {
                    return $product?->getValue();
                },
                'choice_label' => function (ProductUid $product) {
                    return $product->getAttr();
                },

                'choice_attr' => function (?ProductUid $product) {
                    return $product?->getOption() ? ['data-filter' => '('.$product?->getOption().')'] : [];
                },

                'label' => false,
            ]);*/


        /** Все профили пользователя */
        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function(FormEvent $event): void {

                /** @var ProductSignPdfDTO $data */
                $data = $event->getData();
                $form = $event->getForm();

                $user = $this->userProfileTokenStorage->getUser();
                $data->setUsr($user);

                //$profile = $this->userProfileTokenStorage->getProfile();
                //$data->setProfile($profile);

                $profiles = $this->userProfileChoice->getActiveUserProfile($data->getUsr());

                $form
                    ->add('profile', ChoiceType::class, [
                        'choices' => $profiles,
                        'choice_value' => function(?UserProfileUid $profile) {
                            return $profile?->getValue();
                        },
                        'choice_label' => function(UserProfileUid $profile) {
                            return $profile->getAttr();
                        },

                        'label' => false,
                        'required' => false,
                    ]);


                if($data->getCategory())
                {
                    $this->formProductModifier($event->getForm(), $data->getCategory());

                    if($data->getProduct())
                    {
                        $this->formOfferModifier($event->getForm(), $data->getProduct());

                        if($data->getOffer())
                        {
                            $this->formVariationModifier($event->getForm(), $data->getOffer());

                            if($data->getVariation())
                            {
                                $this->formModificationModifier($event->getForm(), $data->getVariation());
                            }
                        }
                    }
                }
            }
        );


        $builder->get('category')->addEventListener(
            FormEvents::POST_SUBMIT,
            function(FormEvent $event) {
                $category = $event->getForm()->getData();
                $this->formProductModifier($event->getForm()->getParent(), $category);
            }
        );


        //        $builder->get('product')->addModelTransformer(
        //            new CallbackTransformer(
        //                function($product) {
        //                    return $product instanceof ProductUid ? $product->getValue() : $product;
        //                },
        //                function($product) {
        //                    return $product ? new ProductUid($product) : null;
        //                }
        //            )
        //        );


        $builder->get('product')->addModelTransformer(
            new CallbackTransformer(
                function($product) {
                    return $product instanceof ProductUid ? $product->getValue() : $product;
                },
                function($product) {
                    return $product ? new ProductUid($product) : null;
                }
            )
        );

        /**
         * Торговые предложения
         * @var ProductOfferConst $offer
         */

        $builder->add(
            'offer',
            HiddenType::class
        );

        $builder->get('offer')->addModelTransformer(
            new CallbackTransformer(
                function($offer) {
                    return $offer instanceof ProductOfferConst ? $offer->getValue() : $offer;
                },
                function($offer) {
                    return $offer ? new ProductOfferConst($offer) : null;
                }
            )
        );


        $builder->get('product')->addEventListener(
            FormEvents::POST_SUBMIT,
            function(FormEvent $event) {
                $product = $event->getForm()->getData();
                $this->formOfferModifier($event->getForm()->getParent(), $product);
            }
        );


        /**
         * Множественный вариант торгового предложения
         * @var ProductVariationConst $variation
         */


        $builder->add(
            'variation',
            HiddenType::class
        );

        $builder->get('variation')->addModelTransformer(
            new CallbackTransformer(
                function($variation) {
                    return $variation instanceof ProductVariationConst ? $variation->getValue() : $variation;
                },
                function($variation) {
                    return $variation ? new ProductVariationConst($variation) : null;
                }
            )
        );

        $builder->get('offer')->addEventListener(
            FormEvents::POST_SUBMIT,
            function(FormEvent $event) {
                $offer = $event->getForm()->getData();
                $this->formVariationModifier($event->getForm()->getParent(), $offer);
            }
        );


        /**
         * Модификатор множественного варианта торгового предложения
         * @var ProductModificationConst $modification
         */

        $builder->add(
            'modification',
            HiddenType::class
        );

        $builder->get('modification')->addModelTransformer(
            new CallbackTransformer(
                function($modification) {
                    return $modification instanceof ProductModificationConst ? $modification->getValue() : $modification;
                },
                function($modification) {
                    return $modification ? new ProductModificationConst($modification) : null;
                }
            )
        );

        $builder->get('variation')->addEventListener(
            FormEvents::POST_SUBMIT,
            function(FormEvent $event) {
                $variation = $event->getForm()->getData();
                $this->formModificationModifier($event->getForm()->getParent(), $variation);
            }
        );


        $builder->add('files', CollectionType::class, [
            'entry_type' => ProductSignFile\ProductSignFileForm::class,
            'entry_options' => ['label' => false],
            'label' => false,
            'by_reference' => false,
            'allow_delete' => true,
            'allow_add' => true,
            'prototype_name' => '__pdf_file__',
        ]);

        $builder->add('purchase', CheckboxType::class, ['required' => false]);


        /* Сохранить ******************************************************/
        $builder->add(
            'product_sign_pdf',
            SubmitType::class,
            ['label' => 'Save', 'label_html' => true, 'attr' => ['class' => 'btn-primary']]
        );


    }

    public function formProductModifier(FormInterface $form, ?CategoryProductUid $category = null): void
    {
        if(null === $category)
        {
            return;
        }

        $products = $this->productChoice->fetchAllProduct($category);

        // Если у продукта нет ТП
        if(!$products)
        {
            return;
        }


        // Продукт
        $form
            ->add('product', ChoiceType::class, [
                'choices' => $products,
                'choice_value' => function(?ProductUid $product) {
                    return $product?->getValue();
                },
                'choice_label' => function(ProductUid $product) {
                    return $product->getAttr();
                },

                'choice_attr' => function(?ProductUid $product) {
                    return $product?->getOption() ? ['data-filter' => '('.$product?->getOption().')'] : [];
                },

                'label' => false
            ]);
    }


    public function formOfferModifier(FormInterface $form, ?ProductUid $product = null): void
    {
        if(null === $product)
        {
            return;
        }

        $offer = $this->productOfferChoice->findByProduct($product);

        // Если у продукта нет ТП
        if(!$offer->valid())
        {
            return;
        }

        $currenOffer = $offer->current();
        $label = $currenOffer->getOption();
        $domain = null;

        if($currenOffer->getProperty())
        {
            /** Если торговое предложение Справочник - ищем домен переводов */
            foreach($this->reference as $reference)
            {
                if($reference->type() === $currenOffer->getProperty())
                {
                    $domain = $reference->domain();
                }
            }
        }


        $form
            ->add('offer', ChoiceType::class, [
                'choices' => $offer,
                'choice_value' => function(?ProductOfferConst $offer) {
                    return $offer?->getValue();
                },
                'choice_label' => function(ProductOfferConst $offer) {
                    return $offer->getAttr();
                },

                'choice_attr' => function(?ProductOfferConst $offer) {
                    return $offer?->getCharacteristic() ? ['data-filter' => ' ('.$offer?->getCharacteristic().')'] : [];
                },

                'label' => $label,
                'translation_domain' => $domain,
                'placeholder' => sprintf('Выберите %s из списка...', $label),
            ]);
    }

    public function formVariationModifier(FormInterface $form, ?ProductOfferConst $offer = null): void
    {

        if(null === $offer)
        {
            return;
        }

        $variations = $this->productVariationChoice->fetchProductVariationByOfferConst($offer);

        // Если у продукта нет множественных вариантов
        if(!$variations->valid())
        {
            return;
        }


        $currenVariation = $variations->current();
        $label = $currenVariation->getOption();
        $domain = null;

        /** Если множественный вариант Справочник - ищем домен переводов */
        if($currenVariation->getProperty())
        {
            foreach($this->reference as $reference)
            {
                if($reference->type() === $currenVariation->getProperty())
                {
                    $domain = $reference->domain();
                }
            }
        }

        $form
            ->add('variation', ChoiceType::class, [
                'choices' => $variations,
                'choice_value' => function(?ProductVariationConst $variation) {
                    return $variation?->getValue();
                },
                'choice_label' => function(ProductVariationConst $variation) {
                    return $variation->getAttr();
                },
                'choice_attr' => function(?ProductVariationConst $variation) {
                    return $variation?->getCharacteristic() ? ['data-filter' => ' ('.$variation?->getCharacteristic().')'] : [];
                },
                'label' => $label,
                'translation_domain' => $domain,
                'placeholder' => sprintf('Выберите %s из списка...', $label),
            ]);
    }

    public function formModificationModifier(FormInterface $form, ?ProductVariationConst $variation = null): void
    {
        if(null === $variation)
        {
            return;
        }

        $modifications = $this->modificationChoice->fetchProductModificationConstByVariationConst($variation);

        // Если у продукта нет модификаций множественных вариантов
        if(!$modifications->valid())
        {
            return;
        }

        $currenModifications = $modifications->current();
        $label = $currenModifications->getOption();
        $domain = null;

        /** Если модификация Справочник - ищем домен переводов */
        if($currenModifications->getProperty())
        {
            foreach($this->reference as $reference)
            {
                if($reference->type() === $currenModifications->getProperty())
                {
                    $domain = $reference->domain();
                }
            }
        }

        $form
            ->add('modification', ChoiceType::class, [
                'choices' => $modifications,
                'choice_value' => function(?ProductModificationConst $modification) {
                    return $modification?->getValue();
                },
                'choice_label' => function(ProductModificationConst $modification) {
                    return $modification->getAttr();
                },
                'choice_attr' => function(?ProductModificationConst $modification) {
                    return $modification?->getCharacteristic() ? ['data-filter' => ' ('.$modification?->getCharacteristic().')'] : [];
                },
                'label' => $label,
                'translation_domain' => $domain,
                'placeholder' => sprintf('Выберите %s из списка...', $label),
            ]);
    }


    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProductSignPdfDTO::class,
            'method' => 'POST',
            'attr' => ['class' => 'w-100'],
        ]);
    }
}
