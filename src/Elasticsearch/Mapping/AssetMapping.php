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

use CIHub\Bundle\SimpleRESTAdapterBundle\Reader\ConfigReader;

final class AssetMapping extends DefaultMapping
{
    public function generate(array $config = []): array
    {
        if ([] === $config) {
            throw new \RuntimeException('No configuration provided.');
        }

        return array_merge($this->getCommonProperties(), [
            'properties' => [
                'binaryData' => [
                    'dynamic' => 'false',
                    'properties' => $this->generateBinaryDataProperties($config),
                ],
                'dimensionData' => [
                    'dynamic' => 'false',
                    'properties' => [
                        'width' => [
                            'type' => 'integer',
                        ],
                        'height' => [
                            'type' => 'integer',
                        ],
                    ],
                ],
                'exifData' => [
                    'dynamic' => 'true',
                    'type' => 'object',
                ],
                'iptcData' => [
                    'dynamic' => 'true',
                    'type' => 'object',
                ],
                'metaData' => [
                    'dynamic' => 'true',
                    'type' => 'object',
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
                        'checksum' => [
                            'type' => 'keyword',
                        ],
                        'mimeType' => [
                            'type' => 'keyword',
                        ],
                        'fileSize' => [
                            'type' => 'long',
                        ],
                    ],
                ],
                'xmpData' => [
                    'dynamic' => 'true',
                    'type' => 'object',
                ],
            ],
        ]);
    }

    /**
     * @param array<string, array> $config
     *
     * @return array<string, array>
     */
    private function generateBinaryDataProperties(array $config): array
    {
        $properties = [];

        $configReader = new ConfigReader($config);
        $thumbnails = $configReader->getAssetThumbnails();
        $binaryMapping = [
            'dynamic' => 'false',
            'type' => 'object',
            'properties' => $this->getBinaryDataProperties(),
        ];

        if ($configReader->isOriginalImageAllowed()) {
            $properties['original'] = $binaryMapping;
        }

        foreach ($thumbnails as $thumbnail) {
            $properties[$thumbnail] = $binaryMapping;
        }

        return $properties;
    }
}
