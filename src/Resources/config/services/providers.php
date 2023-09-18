<?php

declare(strict_types=1);

use CIHub\Bundle\SimpleRESTAdapterBundle\DataCollector\CompositeDataCollector;
use CIHub\Bundle\SimpleRESTAdapterBundle\Provider\AssetProvider;
use CIHub\Bundle\SimpleRESTAdapterBundle\Provider\DataObjectProvider;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(AssetProvider::class)
        ->args([
            '%datahub_rest_adapter.default_preview_thumbnail%',
            service('router.default'),
        ]);

    $services->set(DataObjectProvider::class)
        ->args([
            service(CompositeDataCollector::class),
        ]);
};
