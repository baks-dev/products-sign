<?php

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function(RoutingConfigurator $routes) {


    $MODULE = substr(__DIR__, 0, strpos(__DIR__, "Resources"));

    $routes->import(
        $MODULE.'Controller',
        'attribute',
        false,
        $MODULE.'**/*Test.php'
    )
		->prefix(\BaksDev\Core\Type\Locale\Locale::routes())
		->namePrefix('products-sign:')
	;
};