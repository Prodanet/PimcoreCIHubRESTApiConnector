<?php

declare(strict_types=1);

use CIHub\Bundle\SimpleRESTAdapterBundle\Repository\DataHubConfigurationRepository;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(DataHubConfigurationRepository::class);
};
