<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;

return (new Configuration())
    ->addPathToExclude(__DIR__ . '/tests')
    // Only exists from Symfony 7.3; WishlistVoter accepts it as an optional argument, so it's unknown on 6.4
    ->ignoreUnknownClasses(['Symfony\Component\Security\Core\Authorization\Voter\Vote'])
;
