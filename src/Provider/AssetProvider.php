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

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Provider;

use CIHub\Bundle\SimpleRESTAdapterBundle\Reader\ConfigReader;
use Pimcore\Model\Asset;
use Pimcore\Model\Asset\Document;
use Pimcore\Model\Asset\Folder;
use Pimcore\Model\Asset\Image;
use Pimcore\Model\Asset\Image\Thumbnail;
use Pimcore\Model\Asset\Image\Thumbnail\Config;
use Pimcore\Model\Element\ElementInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Webmozart\Assert\Assert;

final class AssetProvider implements ProviderInterface
{
    /**
     * This thumbnail needs to be passed with every image and document, so CI HUB can display a preview for it.
     */
    public const CIHUB_PREVIEW_THUMBNAIL = 'galleryThumbnail';

    public function __construct(private array $defaultPreviewThumbnail, private RouterInterface $router)
    {
    }

    /**
     * @throws \Exception
     */
    public function getIndexData(ElementInterface $element, ConfigReader $configReader): array
    {
        /* @var Asset $element */
        Assert::isInstanceOf($element, Asset::class);

        $data = [
            'system' => $this->getSystemValues($element),
        ];

        if (!$element instanceof Folder) {
            $data = array_merge($data, array_filter([
                'binaryData' => $this->getBinaryDataValues($element, $configReader),
                'metaData' => $this->getMetaDataValues($element),
            ]));
        }

        if ($element instanceof Image) {
            $data = array_merge($data, array_filter([
                'dimensionData' => [
                    'width' => $element->getWidth(),
                    'height' => $element->getHeight(),
                ],
                'xmpData' => $element->getXMPData() ?: null,
                'exifData' => $element->getEXIFData() ?: null,
                'iptcData' => $element->getIPTCData() ?: null,
            ]));
        }

        return $data;
    }

    /**
     * Returns the binary data values of an asset.
     *
     * @return array<string, array>
     *
     * @throws \Exception
     */
    public function getBinaryDataValues(Asset $asset, ConfigReader $configReader): array
    {
        $data = [];

        $id = $asset->getId();

        try {
            $checksum = $this->getChecksum($asset);
        } catch (\Exception) {
            $checksum = null;
        }

        if ($asset instanceof Image) {
            $thumbnails = $configReader->getAssetThumbnails();

            if ($configReader->isOriginalImageAllowed()) {
                $data['original'] = [
                    'checksum' => $checksum,
                    'path' => $this->router->generate('datahub_rest_endpoints_asset_download', [
                        'config' => $configReader->getName(),
                        'id' => $id,
                    ], UrlGeneratorInterface::ABSOLUTE_PATH),
                    'filename' => $asset->getFilename(),
                ];
            }

            foreach ($thumbnails as $thumbnailName) {
                $thumbnail = $asset->getThumbnail($thumbnailName);

                try {
                    $thumbChecksum = $this->getChecksum($thumbnail->getAsset());
                } catch (\Exception) {
                    $thumbChecksum = null;
                }

                $data[$thumbnailName] = [
                    'checksum' => $thumbChecksum,
                    'path' => $this->router->generate('datahub_rest_endpoints_asset_download', [
                        'config' => $configReader->getName(),
                        'id' => $id,
                        'thumbnail' => $thumbnailName,
                    ], UrlGeneratorInterface::ABSOLUTE_PATH),
                    'filename' => $thumbnail->getAsset()->getFilename(), // pathinfo($thumbnail->getAsset()->getKey(), PATHINFO_BASENAME),
                ];
            }

            // Make sure the preview thumbnail used by CI HUB is added to the list of thumbnails
            if (!\array_key_exists(self::CIHUB_PREVIEW_THUMBNAIL, $data) && 'ciHub' === $configReader->getType()) {
                if (Config::getByName(self::CIHUB_PREVIEW_THUMBNAIL) instanceof Config) {
                    $thumbnail = $asset->getThumbnail(self::CIHUB_PREVIEW_THUMBNAIL);
                } else {
                    $thumbnail = $asset->getThumbnail($this->defaultPreviewThumbnail);
                }

                try {
                    $thumbChecksum = $this->getChecksum($thumbnail->getAsset());
                } catch (\Exception) {
                    $thumbChecksum = null;
                }

                $data[self::CIHUB_PREVIEW_THUMBNAIL] = [
                    'checksum' => $thumbChecksum,
                    'path' => $this->router->generate('datahub_rest_endpoints_asset_download', [
                        'config' => $configReader->getName(),
                        'id' => $id,
                        'thumbnail' => self::CIHUB_PREVIEW_THUMBNAIL,
                    ], UrlGeneratorInterface::ABSOLUTE_PATH),
                    'filename' => $thumbnail->getAsset()->getKey(), // pathinfo($thumbnail->get(), PATHINFO_BASENAME),
                ];
            }
        } else {
            $data['original'] = [
                'checksum' => $checksum,
                'path' => $this->router->generate('datahub_rest_endpoints_asset_download', [
                    'config' => $configReader->getName(),
                    'id' => $id,
                ], UrlGeneratorInterface::ABSOLUTE_PATH),
                'filename' => $asset->getFilename(),
            ];

            // Add the preview thumbnail for CI HUB
            if ($asset instanceof Document && 'ciHub' === $configReader->getType()) {
                if (Config::getByName(self::CIHUB_PREVIEW_THUMBNAIL) instanceof Config) {
                    $thumbnail = $asset->getImageThumbnail(self::CIHUB_PREVIEW_THUMBNAIL);
                } else {
                    $thumbnail = $asset->getImageThumbnail($this->defaultPreviewThumbnail);
                }

                try {
                    $thumbChecksum = $this->getChecksum($thumbnail->getAsset());
                } catch (\Exception) {
                    $thumbChecksum = null;
                }

                $data[self::CIHUB_PREVIEW_THUMBNAIL] = [
                    'checksum' => $thumbChecksum,
                    'path' => $this->router->generate('datahub_rest_endpoints_asset_download', [
                        'config' => $configReader->getName(),
                        'id' => $id,
                        'thumbnail' => self::CIHUB_PREVIEW_THUMBNAIL,
                    ], UrlGeneratorInterface::ABSOLUTE_PATH),
                    'filename' => $thumbnail->getAsset()->getFilename(), // pathinfo($thumbnail->getFileSystemPath(), PATHINFO_BASENAME),
                ];
            }
        }

        return $data;
    }

    /**
     * @throws \Exception
     */
    public function getChecksum(Asset $asset, string $type = 'md5'): ?string
    {
        $localFile = $asset->getLocalFile();
        if (is_file($localFile)) {
            if ('md5' == $type) {
                return md5_file($localFile);
            } elseif ('sha1' == $type) {
                return sha1_file($localFile);
            } else {
                throw new \Exception("hashing algorithm '".$type."' isn't supported");
            }
        }

        return null;
    }

    /**
     * Returns the meta data values of an asset.
     *
     * @return array<string, string>|null
     */
    private function getMetaDataValues(Asset $asset): ?array
    {
        $data = null;
        $metaData = $asset->getMetadata();

        foreach ($metaData as $metumData) {
            $data[$metumData['name']] = $metumData['data'];
        }

        return $data;
    }

    /**
     * Returns the system values of an asset.
     *
     * @return array<string, mixed>
     */
    private function getSystemValues(Asset $asset): array
    {
        $data = [
            'id' => $asset->getId(),
            'key' => $asset->getKey(),
            'fullPath' => $asset->getFullPath(),
            'parentId' => $asset->getParentId(),
            'type' => 'asset',
            'subtype' => $asset->getType(),
            'hasChildren' => $asset->hasChildren(),
            'creationDate' => $asset->getCreationDate(),
            'modificationDate' => $asset->getModificationDate(),
        ];

        if (!$asset instanceof Folder) {
            try {
                $checksum = $this->getChecksum($asset);
            } catch (\Exception) {
                $checksum = null;
            }

            $data = array_merge($data, [
                'checksum' => $checksum,
                'mimeType' => $asset->getMimetype(),
                'fileSize' => $asset->getFileSize(),
            ]);
        }

        return $data;
    }
}
