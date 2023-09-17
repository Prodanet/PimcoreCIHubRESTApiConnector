<?php

declare(strict_types=1);

use CIHub\Bundle\SimpleRESTAdapterBundle\DataCollector\CompositeDataCollector;
use CIHub\Bundle\SimpleRESTAdapterBundle\DataCollector\HotspotImageDataCollector;
use CIHub\Bundle\SimpleRESTAdapterBundle\DataCollector\ImageDataCollector;
use CIHub\Bundle\SimpleRESTAdapterBundle\DataCollector\ImageGalleryDataCollector;
use CIHub\Bundle\SimpleRESTAdapterBundle\Provider\AssetProvider;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(CompositeDataCollector::class)
        ->args([
            tagged_iterator('simple_rest_adapter.data_collector'),
        ]);

    $services->set(HotspotImageDataCollector::class)
        ->args([
            service(ImageDataCollector::class),
        ])
        ->tag('simple_rest_adapter.data_collector', [
            'priority' => 20,
        ]);

    $services->set(ImageDataCollector::class)
        ->args([
            service('router.default'),
            service(AssetProvider::class),
        ])
        ->tag('simple_rest_adapter.data_collector', [
            'priority' => 30,
        ]);

    $services->set(ImageGalleryDataCollector::class)
        ->args([
            service(HotspotImageDataCollector::class),
        ])
        ->tag('simple_rest_adapter.data_collector', [
            'priority' => 10,
        ]);
};
