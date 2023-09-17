<?php
/**
 * Simple REST Adapter.
 *
 * LICENSE
 *
 * This source file is subject to the GNU General Public License version 3 (GPLv3)
 * For the full copyright and license information, please view the LICENSE.md and gpl-3.0.txt
 * files that are distributed with this source code.
 *
 * @copyright  Copyright (c) 2021 CI HUB GmbH (https://ci-hub.com)
 * @license    https://github.com/ci-hub-gmbh/SimpleRESTAdapterBundle/blob/master/gpl-3.0.txt GNU General Public License version 3 (GPLv3)
 */

namespace CIHub\Bundle\SimpleRESTAdapterBundle\DependencyInjection;

use CIHub\Bundle\SimpleRESTAdapterBundle\Elasticsearch\Index\IndexPersistenceService;
use CIHub\Bundle\SimpleRESTAdapterBundle\Elasticsearch\Index\IndexQueryService;
use CIHub\Bundle\SimpleRESTAdapterBundle\Elasticsearch\Mapping\AssetMapping;
use CIHub\Bundle\SimpleRESTAdapterBundle\Elasticsearch\Mapping\DataObjectMapping;
use CIHub\Bundle\SimpleRESTAdapterBundle\Elasticsearch\Mapping\FolderMapping;
use CIHub\Bundle\SimpleRESTAdapterBundle\Extractor\LabelExtractor;
use CIHub\Bundle\SimpleRESTAdapterBundle\Extractor\LabelExtractorInterface;
use CIHub\Bundle\SimpleRESTAdapterBundle\Helper\AssetHelper;
use CIHub\Bundle\SimpleRESTAdapterBundle\Manager\AuthManager;
use CIHub\Bundle\SimpleRESTAdapterBundle\Manager\IndexManager;
use CIHub\Bundle\SimpleRESTAdapterBundle\Provider\AssetProvider;
use CIHub\Bundle\SimpleRESTAdapterBundle\Provider\DataObjectProvider;
use CIHub\Bundle\SimpleRESTAdapterBundle\Repository\DataHubConfigurationRepository;
use Exception;
use Nelmio\ApiDocBundle\Render\RenderOpenApi;
use Pimcore\Bundle\ElasticsearchClientBundle\DependencyInjection\PimcoreElasticsearchClientExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class SimpleRESTAdapterExtension extends Extension implements PrependExtensionInterface
{
    /**
     * @var array
     */
    private array $ciHubConfig = [];

    /**
     * {@inheritdoc}
     */
    public function prepend(ContainerBuilder $container): void
    {
        $bundles = $container->getParameter('kernel.bundles');

        if (isset($bundles['PimcoreCIHubAdapterBundle'])) {
            $this->ciHubConfig = $container->getExtensionConfig('ci_hub_adapter');
        }
    }

    /**
     * {@inheritdoc}
     *
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $this->registerConfiguration($container, $config);

        $loader = new Loader\PhpFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.php');

        $definition = new Definition(IndexManager::class);
        $definition->setArgument('$indexNamePrefix', $config['index_name_prefix']);
        $definition->setArgument('$indexService', new Reference(IndexPersistenceService::class));
        $container->addDefinitions([IndexManager::class => $definition]);

        $definition = new Definition(LabelExtractor::class);
        $definition->setArgument('$indexManager', new Reference(IndexManager::class));
        $container->addDefinitions([LabelExtractor::class => $definition]);

        $definition = new Definition(AssetHelper::class);
        $definition->setArgument('$authManager', new Reference(AuthManager::class));
        $container->addDefinitions([AssetHelper::class => $definition]);


        $container->setAlias(LabelExtractorInterface::class, LabelExtractor::class);
        $container->setAlias(RenderOpenApi::class, 'nelmio_api_doc.render_docs');

        $definition = new Definition(DataHubConfigurationRepository::class);
        $container->addDefinitions([DataHubConfigurationRepository::class => $definition]);

        $definition = new Definition(AuthManager::class);
        $definition->setArgument('$configRepository', new Reference(DataHubConfigurationRepository::class));
        $definition->setArgument('$requestStack', new Reference(RequestStack::class));
        $container->addDefinitions([AuthManager::class => $definition]);

        $definition = new Definition(IndexPersistenceService::class);
        $definition->setArgument('$client', new Reference(PimcoreElasticsearchClientExtension::CLIENT_SERVICE_PREFIX . $config['es_client_name']));
        $definition->setArgument('$configRepository', new Reference(DataHubConfigurationRepository::class));
        $definition->setArgument('$configRepository', new Reference(DataHubConfigurationRepository::class));
        $definition->setArgument('$assetProvider', new Reference(AssetProvider::class));
        $definition->setArgument('$objectProvider', new Reference(DataObjectProvider::class));
        $definition->setArgument('$indexSettings', $config['index_settings']);
        $container->addDefinitions([IndexPersistenceService::class => $definition]);

        $definition = new Definition(IndexQueryService::class);
        $definition->setArgument('$client', new Reference(PimcoreElasticsearchClientExtension::CLIENT_SERVICE_PREFIX . $config['es_client_name']));
        $definition->setArgument('$indexNamePrefix', $config['index_name_prefix']);
        $definition->setArgument('$maxResult', $config['max_result']);
        $container->addDefinitions([IndexQueryService::class => $definition]);

        $definition = new Definition(AssetMapping::class);
        $container->addDefinitions([AssetMapping::class => $definition]);
        $definition = new Definition(DataObjectMapping::class);
        $container->addDefinitions([DataObjectMapping::class => $definition]);
        $definition = new Definition(FolderMapping::class);
        $container->addDefinitions([FolderMapping::class => $definition]);
    }

    /**
     * Registers the configuration as parameters to the container.
     *
     * @param ContainerBuilder            $container
     * @param array<string, string|array> $config
     */
    private function registerConfiguration(ContainerBuilder $container, array $config): void
    {
        if (!empty($this->ciHubConfig)) {
            $config = array_merge($config, ...$this->ciHubConfig);
        }

        $container->setParameter('simple_rest_adapter.index_name_prefix', $config['index_name_prefix']);
        $container->setParameter('simple_rest_adapter.index_settings', $config['index_settings']);
        $container->setParameter('simple_rest_adapter.default_preview_thumbnail', $config['default_preview_thumbnail'] ?? []);
    }
}
