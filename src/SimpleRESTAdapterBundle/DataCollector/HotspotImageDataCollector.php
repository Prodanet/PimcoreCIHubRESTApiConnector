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

namespace CIHub\Bundle\SimpleRESTAdapterBundle\DataCollector;

use CIHub\Bundle\SimpleRESTAdapterBundle\Reader\ConfigReader;
use Exception;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\Data\Hotspotimage;

final class HotspotImageDataCollector implements DataCollectorInterface
{
    /**
     * @var ImageDataCollector
     */
    private ImageDataCollector $imageDataCollector;

    /**
     * @param ImageDataCollector $imageDataCollector
     */
    public function __construct(ImageDataCollector $imageDataCollector)
    {
        $this->imageDataCollector = $imageDataCollector;
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function collect(mixed $value, ConfigReader $reader): array
    {
        $image = $value->getImage();

        if (!$image instanceof Asset\Image) {
            return [];
        }

        return $this->imageDataCollector->collect($image, $reader);
    }

    /**
     * {@inheritdoc}
     */
    public function supports(mixed $value): bool
    {
        return $value instanceof Hotspotimage;
    }
}
