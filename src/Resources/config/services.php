<?php

declare(strict_types=1);

use CIHub\Bundle\SimpleRESTAdapterBundle\Installer;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\expr;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->import(__DIR__ . '/services/*.php');

    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('CIHub\Bundle\SimpleRESTAdapterBundle\Controller\\', __DIR__ . '/../../Controller')
        ->tag('controller.service_arguments');

    $services->set(Installer::class)
        ->public()
        ->arg('$bundle', expr('service(\'kernel\').getBundle(\'SimpleRESTAdapterBundle\')'));
};
