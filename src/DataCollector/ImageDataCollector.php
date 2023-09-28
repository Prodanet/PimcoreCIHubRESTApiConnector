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

use CIHub\Bundle\SimpleRESTAdapterBundle\Provider\AssetProvider;
use CIHub\Bundle\SimpleRESTAdapterBundle\Reader\ConfigReader;
use Pimcore\Model\Asset;
use Symfony\Component\Routing\RouterInterface;

class ImageDataCollector implements DataCollectorInterface
{
    public function __construct(RouterInterface $router, private AssetProvider $assetProvider)
    {
    }

    /**
     * @throws \Exception
     */
    public function collect(mixed $value, ConfigReader $reader): array
    {
        $id = $value->getId();
        $reader->getAssetThumbnails();

        $data = [
            'id' => $id,
            'type' => 'asset',
        ];
        $data['binaryData'] = $this->assetProvider->getBinaryDataValues($value, $reader);

        return $data;
    }

    public function supports(mixed $value): bool
    {
        return $value instanceof Asset\Image;
    }
}
