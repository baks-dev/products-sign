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

namespace BaksDev\Products\Sign\UseCase\Admin\NewEdit\Code;


use BaksDev\Products\Product\Repository\ProductChoice\ProductChoiceInterface;
use BaksDev\Products\Product\Repository\ProductModificationChoice\ProductModificationChoiceInterface;
use BaksDev\Products\Product\Repository\ProductOfferChoice\ProductOfferChoiceInterface;
use BaksDev\Products\Product\Repository\ProductVariationChoice\ProductVariationChoiceInterface;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ProductSignCodeForm extends AbstractType
{

    // private WarehouseChoiceInterface $warehouseChoice;
    private ProductChoiceInterface $productChoice;
    private ProductOfferChoiceInterface $productOfferChoice;
    private ProductVariationChoiceInterface $productVariationChoice;
    private ProductModificationChoiceInterface $modificationChoice;
    private iterable $reference;

    public function __construct(
        // WarehouseChoiceInterface $warehouseChoice,
        ProductChoiceInterface $productChoice,
        ProductOfferChoiceInterface $productOfferChoice,
        ProductVariationChoiceInterface $productVariationChoice,
        ProductModificationChoiceInterface $modificationChoice,
        #[AutowireIterator('baks.reference.choice')] iterable $reference,
    )
    {
        // $this->warehouseChoice = $warehouseChoice;
        $this->productChoice = $productChoice;
        $this->productOfferChoice = $productOfferChoice;
        $this->productVariationChoice = $productVariationChoice;
        $this->modificationChoice = $modificationChoice;
        $this->reference = $reference;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(
                'file', FileType::class,
                [
                    'label' => false,
                    'required' => false,
                    'attr' => ['accept' => ".png, .jpg, .jpeg, .webp, .gif"],
                ]
            );

        $builder->add('code', TextType::class);

        //$builder->add('qr', TextareaType::class, ['required' => false]);


        // Продукт
        $builder
            ->add('product', ChoiceType::class, [
                'choices' => $this->productChoice->fetchAllProduct(),
                'choice_value' => function(?ProductUid $product) {
                    return $product?->getValue();
                },
                'choice_label' => function(ProductUid $product) {
                    return $product->getAttr();
                },


                'label' => false,
            ]);

        /*
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

        $formOfferModifier = function(FormInterface $form, ProductUid $product = null) {
            if(null === $product)
            {
                return;
            }

            $offer = $this->productOfferChoice->fetchProductOfferByProduct($product);


            // Если у продукта нет ТП
            if(empty($offer))
            {
                $form->add(
                    'offer',
                    HiddenType::class
                );

                return;
            }

            $currenOffer = current($offer);
            $label = $currenOffer->getOption();
            $domain = null;


            if($currenOffer->getProperty())
            {
                /** Если торговое предложение Справочник - ищем домен переводов */
                foreach($this->reference as $reference)
                {
                    if($reference->type() === $currenOffer->getProperty()->getType())
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
                    'label' => $label,
                    'translation_domain' => $domain,
                    'placeholder' => sprintf('Выберите %s из списка...', $label),
                ]);
        };


        $builder->get('product')->addEventListener(
            FormEvents::POST_SUBMIT,
            function(FormEvent $event) use ($formOfferModifier) {
                $product = $event->getForm()->getData();
                $formOfferModifier($event->getForm()->getParent(), $product);
            }
        );

        /*
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

        $formVariationModifier = function(FormInterface $form, ProductOfferConst $offer = null) {
            if(null === $offer)
            {
                return;
            }

            $variations = $this->productVariationChoice->fetchProductVariationByOfferConst($offer);

            // Если у продукта нет множественных вариантов
            if(empty($variations))
            {
                $form->add(
                    'variation',
                    HiddenType::class
                );

                return;
            }

            $currenVariation = current($variations);
            $label = $currenVariation->getOption();
            $domain = null;

            /** Если множественный вариант Справочник - ищем домен переводов */
            if($currenVariation->getProperty())
            {
                foreach($this->reference as $reference)
                {
                    if($reference->type() === $currenVariation->getProperty()->getType())
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
                    'label' => $label,
                    'translation_domain' => $domain,
                    'placeholder' => sprintf('Выберите %s из списка...', $label),
                ]);
        };

        $builder->get('offer')->addEventListener(
            FormEvents::POST_SUBMIT,
            function(FormEvent $event) use ($formVariationModifier) {
                $offer = $event->getForm()->getData();
                $formVariationModifier($event->getForm()->getParent(), $offer);
            }
        );

        /*
         * Модификация множественного варианта торгового предложения
         * @var ProductOfferVariationModificationConst $modification
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

        $formModificationModifier = function(FormInterface $form, ProductVariationConst $variation = null) {
            if(null === $variation)
            {
                return;
            }

            $modifications = $this->modificationChoice->fetchProductModificationConstByVariationConst($variation);

            // Если у продукта нет множественных вариантов
            if(empty($modifications))
            {
                $form->add(
                    'modification',
                    HiddenType::class
                );

                return;
            }

            //$label = current($modifications)->getOption();


            $currenModifications = current($modifications);
            $label = $currenModifications->getOption();
            $domain = null;

            /** Если модификация Справочник - ищем домен переводов */
            if($currenModifications->getProperty())
            {
                foreach($this->reference as $reference)
                {
                    if($reference->type() === $currenModifications->getProperty()->getType())
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
                    'label' => $label,
                    'translation_domain' => $domain,
                    'placeholder' => sprintf('Выберите %s из списка...', $label),
                ]);
        };

        $builder->get('variation')->addEventListener(
            FormEvents::POST_SUBMIT,
            function(FormEvent $event) use ($formModificationModifier) {
                $variation = $event->getForm()->getData();
                $formModificationModifier($event->getForm()->getParent(), $variation);
            }
        );


    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProductSignCodeDTO::class,
            'method' => 'POST',
            'attr' => ['class' => 'w-100'],
        ]);
    }
}