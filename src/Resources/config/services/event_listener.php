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

use CIHub\Bundle\SimpleRESTAdapterBundle\Elasticsearch\Mapping\AssetMapping;
use CIHub\Bundle\SimpleRESTAdapterBundle\Elasticsearch\Mapping\DataObjectMapping;
use CIHub\Bundle\SimpleRESTAdapterBundle\Elasticsearch\Mapping\FolderMapping;
use CIHub\Bundle\SimpleRESTAdapterBundle\EventListener\ConfigModificationListener;
use CIHub\Bundle\SimpleRESTAdapterBundle\EventListener\ElementEnqueueingListener;
use CIHub\Bundle\SimpleRESTAdapterBundle\EventListener\ExceptionListener;
use CIHub\Bundle\SimpleRESTAdapterBundle\Guard\WorkspaceGuardInterface;
use CIHub\Bundle\SimpleRESTAdapterBundle\Loader\CompositeConfigurationLoader;
use CIHub\Bundle\SimpleRESTAdapterBundle\Manager\IndexManager;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(ConfigModificationListener::class)
        ->args([
            service(IndexManager::class),
            service('messenger.default_bus'),
            service(AssetMapping::class),
            service(DataObjectMapping::class),
            service(FolderMapping::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ElementEnqueueingListener::class)
        ->args([
            service(CompositeConfigurationLoader::class),
            service(IndexManager::class),
            service('messenger.default_bus'),
            service(WorkspaceGuardInterface::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ExceptionListener::class)
        ->tag('kernel.event_subscriber');
};
