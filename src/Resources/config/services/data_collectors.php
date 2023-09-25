<?php

/**
 * This source file is subject to the GNU General Public License version 3 (GPLv3)
 * For the full copyright and license information, please view the LICENSE.md and gpl-3.0.txt
 * files that are distributed with this source code.
 *
 * @license    https://choosealicense.com/licenses/gpl-3.0/ GNU General Public License v3.0
 * @copyright  Copyright (c) 2023 Brand Oriented sp. z o.o. (https://brandoriented.pl)
 * @copyright  Copyright (c) 2021 CI HUB GmbH (https://ci-hub.com)
 */
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
            tagged_iterator('datahub_rest_adapter.data_collector'),
        ]);

    $services->set(HotspotImageDataCollector::class)
        ->args([
            service(ImageDataCollector::class),
        ])
        ->tag('datahub_rest_adapter.data_collector', [
            'priority' => 20,
        ]);

    $services->set(ImageDataCollector::class)
        ->args([
            service('router.default'),
            service(AssetProvider::class),
        ])
        ->tag('datahub_rest_adapter.data_collector', [
            'priority' => 30,
        ]);

    $services->set(ImageGalleryDataCollector::class)
        ->args([
            service(HotspotImageDataCollector::class),
        ])
        ->tag('datahub_rest_adapter.data_collector', [
            'priority' => 10,
        ]);
};
