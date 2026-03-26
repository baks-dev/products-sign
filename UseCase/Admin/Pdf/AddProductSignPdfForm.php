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
 *
 */

declare(strict_types=1);

namespace BaksDev\Products\Sign\UseCase\Admin\Pdf;

use BaksDev\Products\Sign\UseCase\Admin\Pdf\ProductSignFile\ProductSignFileForm;
use BaksDev\Users\Profile\UserProfile\Repository\UserProfileChoice\UserProfileChoiceInterface;
use BaksDev\Users\Profile\UserProfile\Repository\UserProfileTokenStorage\UserProfileTokenStorageInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class AddProductSignPdfForm extends AbstractType
{
    public function __construct(
        private readonly UserProfileChoiceInterface $userProfileChoice,
        private readonly UserProfileTokenStorageInterface $userProfileTokenStorage,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('number', TextType::class);

        $builder->add('share', CheckboxType::class, ['required' => false]);

        /** Все профили пользователя */
        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function(FormEvent $event): void {

                /** @var ProductSignPdfDTO $data */
                $data = $event->getData();
                $form = $event->getForm();

                $user = $this->userProfileTokenStorage->getUser();
                $data->setUsr($user);

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
                        'required' => true,
                    ]);
            },
        );

        $builder->add('files', CollectionType::class, [
            'entry_type' => ProductSignFileForm::class,
            'entry_options' => ['label' => false],
            'label' => false,
            'by_reference' => false,
            'allow_delete' => true,
            'allow_add' => true,
            'prototype_name' => '__pdf_file__',
        ]);


        $builder->add(
            'add_product_sign_pdf',
            SubmitType::class,
            ['label' => 'Save', 'label_html' => true, 'attr' => ['class' => 'btn-primary']],
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AddProductSignPdfDTO::class,
            'method' => 'POST',
            'attr' => ['class' => 'w-100'],
        ]);
    }
}
