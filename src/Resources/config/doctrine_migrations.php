<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('doctrine_migrations', [
        'migrations_paths' => [
            'CIHub\Bundle\SimpleRESTAdapterBundle\Migrations' => __DIR__ . '/../../Migrations/'
        ]
    ]);
};