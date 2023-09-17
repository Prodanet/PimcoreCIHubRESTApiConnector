<?php

declare(strict_types=1);

use CIHub\Bundle\SimpleRESTAdapterBundle\Loader\CompositeConfigurationLoader;
use CIHub\Bundle\SimpleRESTAdapterBundle\Loader\SimpleRESTConfigurationLoader;
use CIHub\Bundle\SimpleRESTAdapterBundle\Repository\DataHubConfigurationRepository;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(CompositeConfigurationLoader::class)
        ->args([
            service(DataHubConfigurationRepository::class),
            tagged_iterator('pimcore.datahub.configuration.loader'),
        ]);

    $services->set(SimpleRESTConfigurationLoader::class)
        ->tag('pimcore.datahub.configuration.loader');
};
