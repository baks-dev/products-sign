<?php
/*
 *  Copyright 2023.  Baks.dev <admin@baks.dev>
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

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use BaksDev\Products\Product\Type\Event\ProductEventUid;
use BaksDev\Products\Sign\Type\Event\ProductSignEventType;
use BaksDev\Products\Sign\Type\Event\ProductSignEventUid;
use BaksDev\Products\Sign\Type\Id\ProductSignType;
use BaksDev\Products\Sign\Type\Id\ProductSignUid;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus;
use BaksDev\Products\Sign\Type\Status\ProductSignStatusType;
use Symfony\Config\DoctrineConfig;

return static function(ContainerConfigurator $container, DoctrineConfig $doctrine) {
	

	$services = $container->services()
		->defaults()
		->autowire()
		->autoconfigure()
	;
	
	/** Value Resolver */

	$services->set(ProductSignUid::class)->class(ProductSignUid::class);
	$services->set(ProductSignEventUid::class)->class(ProductSignEventUid::class);
	$services->set(ProductSignStatus::class)->class(ProductSignStatus::class);


    /* ProductUid */

    $doctrine->dbal()->type(ProductSignUid::TYPE)->class(ProductSignType::class);
    $doctrine->dbal()->type(ProductSignEventUid::TYPE)->class(ProductSignEventType::class);
    $doctrine->dbal()->type(ProductSignStatus::TYPE)->class(ProductSignStatusType::class);

    $emDefault = $doctrine->orm()->entityManager('default')->autoMapping(true);

    $MODULE = substr(__DIR__, 0, strpos(__DIR__, "Resources"));

    $emDefault->mapping('products-sign')
		->type('attribute')
		->dir($MODULE.'Entity')
		->isBundle(false)
		->prefix('BaksDev\Products\Sign\Entity')
		->alias('products-sign ')
	;
};