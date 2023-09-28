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

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Elasticsearch\Mapping;

class DataObjectMapping extends DefaultMapping
{
    public function generate(array $config = []): array
    {
        if ([] === $config) {
            throw new \RuntimeException('No DataObject class configuration provided.');
        }

        return [...$this->getCommonProperties(), 'properties' => [
            'data' => [
                'dynamic' => 'true',
                'properties' => $this->generateDataProperties($config),
            ],
            'system' => [
                'dynamic' => 'false',
                'properties' => [
                    'id' => [
                        'type' => 'long',
                    ],
                    'key' => [
                        'type' => 'keyword',
                        'fields' => [
                            'analyzed' => [
                                'type' => 'text',
                                'term_vector' => 'yes',
                                'analyzer' => 'datahub_ngram_analyzer',
                                'search_analyzer' => 'datahub_whitespace_analyzer',
                            ],
                        ],
                    ],
                    'fullPath' => [
                        'type' => 'keyword',
                        'fields' => [
                            'analyzed' => [
                                'type' => 'text',
                                'term_vector' => 'yes',
                                'analyzer' => 'datahub_ngram_analyzer',
                                'search_analyzer' => 'datahub_whitespace_analyzer',
                            ],
                        ],
                    ],
                    'type' => [
                        'type' => 'constant_keyword',
                    ],
                    'parentId' => [
                        'type' => 'keyword',
                    ],
                    'hasChildren' => [
                        'type' => 'boolean',
                    ],
                    'creationDate' => [
                        'type' => 'date',
                    ],
                    'modificationDate' => [
                        'type' => 'date',
                    ],
                    'subtype' => [
                        'type' => 'keyword',
                    ],
                ],
            ],
        ]];
    }

    /**
     * Generates all data properties for the given DataObject class config.
     *
     * @param array<string, array|string> $config
     *
     * @return array<string, array>
     */
    private function generateDataProperties(array $config): array
    {
        $properties = [];
        $columnConfig = $config['columnConfig'] ?? [];

        foreach ($columnConfig as $column) {
            if (true === $column['hidden']) {
                continue;
            }

            $properties[$column['name']] = $this->getPropertiesForFieldConfig($column['fieldConfig']);
        }

        return $properties;
    }

    /**
     * Generates the property definition for a given field config.
     *
     * @param array<string, array|string> $config
     *
     * @return array<string, array|string>
     */
    private function getPropertiesForFieldConfig(array $config): array
    {
        return match ($config['type']) {
            'hotspotimage', 'image' => [...$this->getImageProperties(), 'dynamic' => 'false', 'type' => 'object'],
            'imageGallery' => [...$this->getImageProperties(), 'dynamic' => 'false', 'type' => 'nested'],
            'numeric' => [
                'type' => $config['layout']['integer'] ? 'integer' : 'float',
            ],
            default => [
                'type' => 'keyword',
                'fields' => [
                    'analyzed' => [
                        'type' => 'text',
                        'term_vector' => 'yes',
                        'analyzer' => 'datahub_ngram_analyzer',
                        'search_analyzer' => 'datahub_whitespace_analyzer',
                    ],
                ],
            ],
        };
    }
}
