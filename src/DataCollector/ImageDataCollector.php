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
    private RouterInterface $router;

    private AssetProvider $assetProvider;

    public function __construct(RouterInterface $router, AssetProvider $assetProvider)
    {
        $this->router = $router;
        $this->assetProvider = $assetProvider;
    }

    /**
     * @throws \Exception
     */
    public function collect(mixed $value, ConfigReader $reader): array
    {
        $id = $value->getId();
        $thumbnails = $reader->getAssetThumbnails();

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

    /**
     * @throws \Exception
     */
    private function getChecksum(Asset $asset, string $type = 'md5'): ?string
    {
        $file = $asset->getLocalFile();
        if (is_file($file)) {
            if ('md5' == $type) {
                return md5_file($file);
            } elseif ('sha1' == $type) {
                return sha1_file($file);
            } else {
                throw new \Exception("hashing algorithm '" . $type . "' isn't supported");
            }
        }

        return null;
    }
}
