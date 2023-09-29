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

namespace CIHub\Bundle\SimpleRESTAdapterBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('datahub_rest_adapter');
        $treeBuilder->getRootNode()
            ->addDefaultsIfNotSet()
            ->children()
            ->scalarNode('index_name_prefix')
            ->info('Prefix for index names.')
            ->defaultValue('datahub_restindex')
            ->validate()
            ->ifString()
            ->then(static fn($value): string => rtrim(str_replace('-', '_', $value), '_'))
            ->end()
            ->end()
            ->scalarNode('es_client_name')
            ->info('Name of elasticsearch client configuration to be used.')
            ->defaultValue('default')
            ->end()
            ->scalarNode('max_result')
            ->info('Maximum result for page')
            ->defaultValue(100)
            ->end()
            ->variableNode('index_settings')
            ->info('Global Elasticsearch index settings.')
            ->defaultValue([
                'number_of_shards' => 5,
                'number_of_replicas' => 0,
                'max_ngram_diff' => 20,
                'analysis' => [
                    'analyzer' => [
                        'datahub_ngram_analyzer' => [
                            'type' => 'custom',
                            'tokenizer' => 'datahub_ngram_tokenizer',
                            'filter' => ['lowercase'],
                        ],
                        'datahub_whitespace_analyzer' => [
                            'type' => 'custom',
                            'tokenizer' => 'datahub_whitespace_tokenizer',
                            'filter' => ['lowercase'],
                        ],
                    ],
                    'normalizer' => [
                        'lowercase' => [
                            'type' => 'custom',
                            'filter' => ['lowercase'],
                        ],
                    ],
                    'tokenizer' => [
                        'datahub_ngram_tokenizer' => [
                            'type' => 'ngram',
                            'min_gram' => 2,
                            'max_gram' => 20,
                            'token_chars' => ['letter', 'digit'],
                        ],
                        'datahub_whitespace_tokenizer' => [
                            'type' => 'whitespace',
                        ],
                    ],
                ],
            ])
            ->end()
            ->end();

        return $treeBuilder;
    }
}
