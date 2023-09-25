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
