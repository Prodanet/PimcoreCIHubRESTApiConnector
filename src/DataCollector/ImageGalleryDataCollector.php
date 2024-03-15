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

namespace CIHub\Bundle\SimpleRESTAdapterBundle\DataCollector;

use CIHub\Bundle\SimpleRESTAdapterBundle\Reader\ConfigReader;
use Pimcore\Model\DataObject\Data\ImageGallery;

final readonly class ImageGalleryDataCollector implements DataCollectorInterface
{
    public function __construct(private HotspotImageDataCollector $hotspotImageDataCollector)
    {
    }

    /**
     * @throws \Exception
     */
    public function collect(mixed $value, ConfigReader $configReader): array
    {
        $data = [];
        $items = $value->getItems() ?? [];

        foreach ($items as $item) {
            $data[] = $this->hotspotImageDataCollector->collect($item, $configReader);
        }

        return $data;
    }

    public function supports(mixed $value): bool
    {
        return $value instanceof ImageGallery;
    }
}
