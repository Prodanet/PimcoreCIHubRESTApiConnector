<?php

declare(strict_types=1);

use CIHub\Bundle\SimpleRESTAdapterBundle\Extractor\LabelExtractor;
use CIHub\Bundle\SimpleRESTAdapterBundle\Extractor\LabelExtractorInterface;
use CIHub\Bundle\SimpleRESTAdapterBundle\Manager\IndexManager;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->alias(LabelExtractorInterface::class, LabelExtractor::class);

    $services->set(LabelExtractor::class)
        ->args([
            service(IndexManager::class),
        ]);
};
