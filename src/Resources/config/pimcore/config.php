<?php

declare(strict_types=1);

use CIHub\Bundle\SimpleRESTAdapterBundle\Messenger\DeleteIndexElementMessage;
use CIHub\Bundle\SimpleRESTAdapterBundle\Messenger\InitializeEndpointMessage;
use CIHub\Bundle\SimpleRESTAdapterBundle\Messenger\UpdateIndexElementMessage;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('pimcore_admin', [
        'admin_csp_header' => [
            'exclude_paths' => [
                service('^/admin/datahub/rest/swagger@'),
            ],
        ],
    ]);

    $containerConfigurator->extension('nelmio_api_doc', [
        'documentation' => [
            'components' => [
                'securitySchemes' => [
                    'Bearer' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                    ],
                ],
            ],
            'openapi' => '3.0.0',
            'security' => [
                [
                    'Bearer' => [
                    ],
                ],
            ],
            'info' => [
                'title' => 'Pimcore DataHub REST Adapter',
                'description' => 'Endpoints provided by the REST Adapter Bundle.',
                'version' => '2.0.0',
                'license' => [
                    'name' => 'GPL 3.0',
                    'url' => 'https://www.gnu.org/licenses/gpl-3.0.html',
                ],
            ],
        ],
        'areas' => [
            'disable_default_routes' => true,
            'ci_hub' => [
                'path_patterns' => [
                    '^/datahub/rest/{config}(?!/doc$)',
                ],
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
