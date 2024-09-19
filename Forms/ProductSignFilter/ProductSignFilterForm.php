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

namespace BaksDev\Products\Sign\Forms\ProductSignFilter;

use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\Collection\ProductSignStatusCollection;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\Collection\ProductSignStatusInterface;
use BaksDev\Wildberries\Orders\Forms\WbOrdersStatusFilter\WbOrdersStatusFilterDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ProductSignFilterForm extends AbstractType
{
    private string $sessionKey;

    private SessionInterface|false $session = false;

    public function __construct(
        private readonly ProductSignStatusCollection $status,
        private readonly RequestStack $request
    ) {
        $this->sessionKey = md5(self::class);
    }


    public function buildForm(FormBuilderInterface $builder, array $options): void
    {


        $builder->add('status', ChoiceType::class, [
            'choices' => $this->status->cases(),
            'choice_value' => function (?ProductSignStatusInterface $region) {
                return $region?->getValue();
            },
            'choice_label' => function (ProductSignStatusInterface $region) {
                return $region->getValue();
            },
            'label' => false,
            'translation_domain' => 'products-sign.status'
        ]);


        $builder->add('from', DateType::class, [
            'widget' => 'single_text',
            'html5' => false,
            'attr' => ['class' => 'js-datepicker'],
            'required' => false,
            'format' => 'dd.MM.yyyy',
            'input' => 'datetime_immutable',
        ]);


        $builder->add('to', DateType::class, [
            'widget' => 'single_text',
            'html5' => false,
            'attr' => ['class' => 'js-datepicker'],
            'required' => false,
            'format' => 'dd.MM.yyyy',
            'input' => 'datetime_immutable',
        ]);

        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event): void {
                /** @var ProductSignFilterDTO $data */
                $data = $event->getData();

                if($this->session === false)
                {
                    $this->session = $this->request->getSession();
                }

                if($this->session && $this->session->get('statusCode') === 307)
                {
                    $this->session->remove($this->sessionKey);
                    $this->session = false;
                }

                if($this->session && (time() - $this->session->getMetadataBag()->getLastUsed()) > 300)
                {
                    $this->session->remove($this->sessionKey);
                    $this->session = false;
                }

                if($this->session)
                {
                    $sessionData = $this->request->getSession()->get($this->sessionKey);
                    $sessionJson = $sessionData ? base64_decode($sessionData) : false;
                    $sessionArray = $sessionJson !== false && json_validate($sessionJson) ? json_decode($sessionJson, true) : [];

                    $data->setStatus($sessionArray['status'] ?? null);
                    $data->setFrom($sessionArray['from']['date'] ?? null);
                    $data->setTo($sessionArray['to']['date'] ?? null);
                }
            }
        );

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event): void {
                /** @var ProductSignFilterDTO $data */
                $data = $event->getData();

                if($this->session === false)
                {
                    $this->session = $this->request->getSession();
                }

                if($this->session)
                {
                    $sessionArray = [];

                    $data->getStatus() ? $sessionArray['status'] = $data->getStatus()->getValue() : false;
                    $data->getFrom() ? $sessionArray['from'] = $data->getFrom() : false;
                    $data->getTo() ? $sessionArray['to'] = $data->getTo() : false;

                    if($sessionArray)
                    {
                        $sessionJson = json_encode($sessionArray);
                        $sessionData = base64_encode($sessionJson);
                        $this->request->getSession()->set($this->sessionKey, $sessionData);
                        return;
                    }

                    $this->request->getSession()->remove($this->sessionKey);

                }
            }
        );


        /* Сохранить ******************************************************/
        /*$builder->add(
            'product_sign_filter',
            SubmitType::class,
            ['label' => 'Save', 'label_html' => true, 'attr' => ['class' => 'btn-primary']]
        );*/
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProductSignFilterDTO::class,
            'method' => 'POST',
            'attr' => ['class' => 'w-100'],
        ]);
    }
}
