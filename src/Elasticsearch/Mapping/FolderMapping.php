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

class FolderMapping extends DefaultMapping
{
    public function generate(array $config = []): array
    {
        return [...$this->getCommonProperties(), 'properties' => [
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
                        'type' => 'constant_keyword',
                    ],
                ],
            ],
        ]];
    }
}
