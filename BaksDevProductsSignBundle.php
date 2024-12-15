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

namespace BaksDev\Products\Sign;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class BaksDevProductsSignBundle extends AbstractBundle
{
    public const string NAMESPACE = __NAMESPACE__.'\\';

    public const string PATH = __DIR__.DIRECTORY_SEPARATOR;

//    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    //    {
    //        $services = $container->services()
    //            ->defaults()
    //            ->autowire()
    //            ->autoconfigure();
    //
    //        $services->load(self::NAMESPACE, self::PATH)
    //            ->exclude([
    //                self::PATH.'{Entity,Resources,Type}',
    //                self::PATH.'**/*Message.php',
    //                self::PATH.'**/*DTO.php',
    //            ]);
    //
    //
    //        /* Статусы заказов */
    //        $services->load(
    //            self::NAMESPACE.'Type\Status\ProductSignStatus\\',
    //            self::PATH.'Type/Status/ProductSignStatus'
    //        );
    //
    //        /** @see https://symfony.com/doc/current/service_container/autowiring.html#dealing-with-multiple-implementations-of-the-same-type */
    //        $services->alias(ProductSignStatusInterface::class.' $productSignStatusNew', ProductSignStatusNew::class);
    //        $services->alias(ProductSignStatusInterface::class.' $productSignStatusDone', ProductSignStatusDone::class);
    //
    //        $services->alias(ProductSignStatusInterface::class, ProductSignStatusNew::class);
    //    }
}
