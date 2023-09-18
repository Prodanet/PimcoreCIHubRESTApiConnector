<?php

declare(strict_types=1);

use CIHub\Bundle\SimpleRESTAdapterBundle\Guard\WorkspaceGuard;
use CIHub\Bundle\SimpleRESTAdapterBundle\Guard\WorkspaceGuardInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->alias(WorkspaceGuardInterface::class, WorkspaceGuard::class);

    $services->set(WorkspaceGuard::class);
};
