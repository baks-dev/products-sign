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

namespace BaksDev\Products\Sign\Controller\Admin;

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Core\Messenger\MessageDelay;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Orders\Order\Entity\Products\OrderProduct;
use BaksDev\Orders\Order\Repository\ExistOrderEventByStatus\ExistOrderEventByStatusInterface;
use BaksDev\Orders\Order\Type\Status\OrderStatus\Collection\OrderStatusCompleted;
use BaksDev\Orders\Order\Type\Status\OrderStatus\Collection\OrderStatusPackage;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierByEventInterface;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Sign\Forms\ProductsSignsReissue\ProductsSignsReissueDTO;
use BaksDev\Products\Sign\Forms\ProductsSignsReissue\ProductsSignsReissueForm;
use BaksDev\Products\Sign\Messenger\ProductSignStatus\ProductSignCancel\ProductSignCancelMessage;
use BaksDev\Products\Sign\Messenger\ProductSignStatus\ProductSignProcess\ProductSignProcessMessage;
use BaksDev\Products\Sign\Repository\ProductSignProcessByOrder\ProductSignProcessByOrderInterface;
use BaksDev\Products\Sign\Type\Id\ProductSignUid;
use JsonException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[RoleSecurity('ROLE_PRODUCT_SIGN_REISSUE')]
final class ReissueController extends AbstractController
{
    /**
     * @throws JsonException
     */
    #[Route('/admin/product/signs/reissue/{id}', name: 'admin.reissue', methods: ['GET', 'POST'])]
    public function reissue(
        #[MapEntity] OrderEvent $OrderEvent,
        Request $request,
        MessageDispatchInterface $MessageDispatch,
        ProductSignProcessByOrderInterface $ProductSignProcessByOrderRepository,
        ExistOrderEventByStatusInterface $ExistOrderEventByStatusRepository,
        CurrentProductIdentifierByEventInterface $CurrentProductIdentifierRepository,
    ): Response
    {
        $productsSignsReissueDTO = new ProductsSignsReissueDTO()
            ->setOrder($OrderEvent->getMain());

        $form = $this
            ->createForm(
                type: ProductsSignsReissueForm::class,
                data: $productsSignsReissueDTO,
                options: ['action' => $this->generateUrl(
                    'products-sign:admin.reissue',
                    ['id' => $OrderEvent->getId()],
                )],
            )
            ->handleRequest($request);

        if($form->isSubmitted() && $form->isValid() & $form->has('product_signs_reissue'))
        {
            /**
             * Проверяем, был ли или находится ли данный заказ в статусе "Упаковка"
             */

            $existsPackageStatus = $ExistOrderEventByStatusRepository
                ->forOrder($OrderEvent->getMain())
                ->forStatus(OrderStatusPackage::STATUS)
                ->isExists();

            if(false === $existsPackageStatus)
            {
                $flash = $this->addFlash
                (
                    'page.reissue',
                    'danger.reissue.package',
                    'products-sign.admin',
                );

                return $flash ?: $this->redirectToReferer();
            }


            /**
             * Проверяем, был ли данный заказ выполнен
             */

            $existsCompletedStatus = $ExistOrderEventByStatusRepository
                ->forOrder($OrderEvent->getMain())
                ->forStatus(OrderStatusCompleted::STATUS)
                ->isExists();

            if(true === $existsCompletedStatus)
            {
                $this->addFlash
                (
                    'page.reissue',
                    'danger.reissue.completed',
                    'products-sign.admin',
                );

                return new JsonResponse('Cannot reissue product signs on order completed', 400);
            }

            /**
             * Получаем все честные знаки для данного заказа
             */

            $signs = $ProductSignProcessByOrderRepository
                ->forOrder($OrderEvent->getMain())
                ->findAll();

            foreach($signs as $ProductSignEventUid)
            {
                /** Отменяем честный знак */
                $MessageDispatch->dispatch(
                    message: new ProductSignCancelMessage(
                        $this->getProfileUid(),
                        $ProductSignEventUid,
                    ),
                    transport: 'products-sign',
                );
            }

            /**
             * Отправляем запросы на повторное резервирование
             */

            foreach($OrderEvent->getProduct() as $OrderProduct)
            {
                /** Получаем текущие идентификаторы */
                $CurrentProductIdentifierResult = $CurrentProductIdentifierRepository
                    ->forEvent($OrderProduct->getProduct())
                    ->forOffer($OrderProduct->getOffer())
                    ->forVariation($OrderProduct->getVariation())
                    ->forModification($OrderProduct->getModification())
                    ->find();

                $ProductSignPart = new ProductSignUid();

                $ProductSignProcessMessage = new ProductSignProcessMessage(
                    order: $OrderEvent->getMain(),
                    part: $ProductSignPart,
                    user: $this->getUsr()?->getId(),
                    profile: $this->getProfileUid(),
                    product: $CurrentProductIdentifierResult->getProduct(),
                    offer: $CurrentProductIdentifierResult->getOfferConst(),
                    variation: $CurrentProductIdentifierResult->getVariationConst(),
                    modification: $CurrentProductIdentifierResult->getModificationConst(),
                );

                $productTotal = $OrderProduct->getTotal();

                for($i = 1; $i <= $productTotal; $i++)
                {
                    $ProductSignProcessMessage->setPart($ProductSignPart);

                    $MessageDispatch
                        ->dispatch(
                            message: $ProductSignProcessMessage,
                            stamps: [new MessageDelay('5 seconds')],
                            transport: 'products-sign',
                        );

                    /** Разбиваем по 100 шт */
                    if(($i % 100) === 0)
                    {
                        $ProductSignPart = new ProductSignUid();
                    }
                }
            }

            $this->addFlash
            (
                'page.reissue',
                'success.reissue',
                'products-sign.admin',
            );

            return $this->redirectToReferer();
        }

        return $this->render(['form' => $form->createView()]);
    }
}