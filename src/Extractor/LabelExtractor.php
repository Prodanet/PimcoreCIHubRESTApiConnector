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

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Extractor;

use CIHub\Bundle\SimpleRESTAdapterBundle\Manager\IndexManager;

final class LabelExtractor implements LabelExtractorInterface
{
    public const ALLOWED_PROPERTIES = ['data', 'dimensionData', 'metaData', 'system'];
    public const ALLOWED_SYSTEM_PROPERTIES = ['id', 'key', 'mimeType', 'subtype', 'type'];

    private IndexManager $indexManager;

    public function __construct(IndexManager $indexManager)
    {
        $this->indexManager = $indexManager;
    }

    public function extractLabels(array $indices): array
    {
        $labels = [];

        foreach ($indices as $index) {
            $mapping = $this->indexManager->getIndexMapping($index);

            if (empty($mapping)) {
                continue;
            }

            foreach ($mapping['properties'] as $property => $definition) {
                if (!\in_array($property, self::ALLOWED_PROPERTIES, true)) {
                    continue;
                }

                $labels[] = array_map(
                    static function ($item) use ($property) {
                        return sprintf('%s.%s', $property, $item);
                    },
                    array_filter(
                        array_keys($definition['properties'] ?? []),
                        static function ($key) use ($property) {
                            return 'system' !== $property
                                || \in_array($key, self::ALLOWED_SYSTEM_PROPERTIES, true);
                        }
                    )
                );
            }
        }

        return array_values(array_unique(array_merge([], ...$labels)));
    }
}
