<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
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
                'version' => '2.1.0',
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
};
