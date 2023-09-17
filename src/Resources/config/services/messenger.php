<?php

declare(strict_types=1);

use CIHub\Bundle\SimpleRESTAdapterBundle\Elasticsearch\Index\IndexPersistenceService;
use CIHub\Bundle\SimpleRESTAdapterBundle\Manager\IndexManager;
use CIHub\Bundle\SimpleRESTAdapterBundle\Messenger\Handler\DeleteIndexElementMessageHandler;
use CIHub\Bundle\SimpleRESTAdapterBundle\Messenger\Handler\InitializeEndpointMessageHandler;
use CIHub\Bundle\SimpleRESTAdapterBundle\Messenger\Handler\UpdateIndexElementMessageHandler;
use CIHub\Bundle\SimpleRESTAdapterBundle\Repository\DataHubConfigurationRepository;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(DeleteIndexElementMessageHandler::class)
        ->args([
            service(IndexPersistenceService::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(InitializeEndpointMessageHandler::class)
        ->args([
            service(DataHubConfigurationRepository::class),
            service('database_connection'),
            service('messenger.default_bus'),
        ])
        ->tag('messenger.message_handler');

    $services->set(UpdateIndexElementMessageHandler::class)
        ->args([
            service(IndexManager::class),
            service(IndexPersistenceService::class),
        ])
        ->tag('messenger.message_handler');
};
