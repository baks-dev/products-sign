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

namespace BaksDev\Products\Sign\Commands;


use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Orders\Order\Repository\AllOrders\AllOrdersInterface;
use BaksDev\Orders\Order\Repository\AllOrders\AllOrdersResult;
use BaksDev\Orders\Order\Type\Status\OrderStatus\Collection\OrderStatusCanceled;
use BaksDev\Products\Sign\Messenger\ProductSignStatus\ProductSignCancel\ProductSignCancelMessage;
use BaksDev\Products\Sign\Repository\ProductSignByOrder\ProductSignByOrderInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use function Symfony\Component\Translation\t;

#[AsCommand(
    name: 'baks:products:sign:cancel-order',
    description: 'Находит и отменяет честные знаки на отмененные заказы'
)]
class CancelProductsSignByOrderCancelCommand extends Command
{
    public function __construct(
        private readonly AllOrdersInterface $AllOrdersRepository,
        private readonly ProductSignByOrderInterface $ProductSignByOrderRepository,
        private readonly MessageDispatchInterface $MessageDispatch
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('argument', InputArgument::OPTIONAL, 'Описание аргумента');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);


        $UserProfileUid = new UserProfileUid('019577a9-71a3-714b-a99c-0386833d802f');

        /** Получаем все заказы со статусом Canceled «Отменен» */
        $AllOrdersRepository = $this->AllOrdersRepository
            ->status(OrderStatusCanceled::class)
            ->forProfile($UserProfileUid)
            ->setLimit(100);

        $page = 0;

        while(true)
        {
            $orders = $AllOrdersRepository
                ->findPaginator()
                ->setPage($page)
                ->getData();

            if(empty($orders))
            {
                return Command::SUCCESS;
            }

            /** @var AllOrdersResult $order */
            foreach($orders as $order)
            {
                $result = $this->ProductSignByOrderRepository
                    ->forOrder($order->getOrderId())
                    ->findAll();

                if(false === $result || false === $result->valid())
                {
                    $io->writeln(sprintf('<fg=gray>- %s</>', $order->getOrderId()));
                    continue;
                }

                $io->writeln(sprintf('<fg=green>+ %s</>', $order->getOrderId()));

                foreach($result as $ProductSignByOrderResult)
                {
                    $ProductSignCancelMessage = new ProductSignCancelMessage(
                        $UserProfileUid,
                        $ProductSignByOrderResult->getSignEvent(),
                    );

                    /*$this->MessageDispatch->dispatch(
                        message: $ProductSignCancelMessage,
                    );*/
                }

                dump($order->getOrderNumber()); /* TODO: удалить !!! */
            }

            $page++;

        }


        $io->success('Честные знаки успешно отменены');

        return Command::SUCCESS;
    }
}
