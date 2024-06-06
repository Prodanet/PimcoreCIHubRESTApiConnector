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
    public function extractMapping(ConfigReader $config = null): array
    {
        $systemDataMapping = $this->createSystemAttributesMapping();
        $systemDataMapping['properties']['checksum'] = ['type' => 'keyword'];
        $systemDataMapping['properties']['mimeType'] = ['type' => 'keyword'];
        $systemDataMapping['properties']['fileSize'] = ['type' => 'long'];
        $systemDataMapping['properties']['versionCount'] = ['type' => 'integer'];

        $data = [

            'system' => $systemDataMapping,

            'dimensionData' => [
                'type' => 'object',
                'dynamic' => false,
                'properties' => [
                    'height' => ['type' => 'integer'],
                    'width' => ['type' => 'integer']
                ]
            ],

            'xmpData' => [
                'type' => 'object',
                'dynamic' => true,
            ],

            'exifData' => [
                'type' => 'object',
                'dynamic' => true,
            ],

            'iptcData' => [
                'type' => 'object',
                'dynamic' => true,
            ],

            'metaData' => [
                'type' => 'object',
                'dynamic' => true
            ],
        ];

        $thumbnails = [
            'original' => [
                'type' => 'object',
                'dynamic' => false,
                'properties' => [
                    'path' => ['type' => 'keyword'],
                    'checksum' => ['type' => 'keyword']
                ]
            ]
        ];

        foreach ($config->getAssetThumbnails() as $thumbnail) {
            $thumbnails[$thumbnail] = [
                'type' => 'object',
                'dynamic' => false,
                'properties' => [
                    'path' => ['type' => 'keyword'],
                    'checksum' => ['type' => 'keyword']
                ]
            ];
        }

        $data['binaryData'] = [
            'type' => 'object',
            'dynamic' => false,
            'properties' => $thumbnails
        ];

        return $data;
    }

    public function generate(ConfigReader $config = null): array
    {
        $mappingProperties = $this->extractMapping($config);
        $mappings = $this->mappingTemplate;
        $mappings['properties'] = $mappingProperties;

        return $mappings;
    }
}
