<?php

declare(strict_types=1);

use CIHub\Bundle\SimpleRESTAdapterBundle\Messenger\DeleteIndexElementMessage;
use CIHub\Bundle\SimpleRESTAdapterBundle\Messenger\InitializeEndpointMessage;
use CIHub\Bundle\SimpleRESTAdapterBundle\Messenger\UpdateIndexElementMessage;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('pimcore_admin', [
        'admin_csp_header' => [
            'exclude_paths' => [
                '@^/admin/datahub/rest/swagger@',
            ],
        ],
    ]);

    $containerConfigurator->extension('framework', [
        'messenger' => [
            'transports' => [
                'datahub_es_index_queue' => 'doctrine://default?queue_name=datahub_es_index_queue',
            ],
            'routing' => [
                DeleteIndexElementMessage::class => 'datahub_es_index_queue',
                InitializeEndpointMessage::class => 'datahub_es_index_queue',
                UpdateIndexElementMessage::class => 'datahub_es_index_queue',
            ],
        ],
    ]);
};
