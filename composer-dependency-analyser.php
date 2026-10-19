<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration())
    ->addPathToExclude(__DIR__ . '/tests')
    // Only used from the shop templates (layouts, macros, components and Twig hooks) and ux_icon(), which the analyser doesn't scan
    ->ignoreErrorsOnPackages(['sylius/shop-bundle', 'symfony/ux-icons'], [ErrorType::UNUSED_DEPENDENCY])
;
