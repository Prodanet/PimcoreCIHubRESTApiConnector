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

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Traits;

use Pimcore\Model\Element\Tag;

trait TagsFilterTrait
{
    private function mergeNestedItems($array): array
    {
        $mergedItems = [];
        foreach ($array as $item) {
            if (isset($item['items'])) {
                array_walk_recursive($array, static function ($a) use (&$return): void {
                    $return[]['label'] = $a;
                });
                $mergedItems = $return;
            } else {
                $mergedItems[] = $item;
            }
        }

        return $mergedItems;
    }

    private function mergeTopLevelItems($data): array
    {
        $mergedData = [];
        foreach ($data as $item) {
            if (isset($item['items']) && \is_array($item['items'])) {
                $item['items'] = $this->mergeNestedItems($item['items']);
            }

            $mergedData[] = $item;
        }

        return $mergedData;
    }

    private function convertTagToArray(Tag $tag): array
    {
        $tagArray = [
            'label' => $tag->getName(),
        ];

        if ($tag->hasChildren()) {
            foreach ($tag->getChildren() as $child) {
                $tagArray['items'][] = $this->convertTagToArray($child);
            }
        }

        return $tagArray;
    }
}
