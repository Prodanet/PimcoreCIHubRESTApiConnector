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

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Controller;

use CIHub\Bundle\SimpleRESTAdapterBundle\Elasticsearch\Index\IndexQueryService;
use CIHub\Bundle\SimpleRESTAdapterBundle\Exception\AssetNotFoundException;
use CIHub\Bundle\SimpleRESTAdapterBundle\Helper\AssetHelper;
use CIHub\Bundle\SimpleRESTAdapterBundle\Manager\IndexManager;
use CIHub\Bundle\SimpleRESTAdapterBundle\Provider\AssetProvider;
use CIHub\Bundle\SimpleRESTAdapterBundle\Reader\ConfigReader;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Attributes as OA;
use Pimcore\Model\Asset;
use Pimcore\Model\Element\Service;
use Pimcore\Model\Version;
use Pimcore\Model\Version\Listing;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: ['/datahub/rest/{config}', '/pimcore-datahub-webservices/simplerest/{config}'], name: 'datahub_rest_endpoints_')]
#[Security(name: 'Bearer')]
#[OA\Tag(name: 'Asset')]
class AssetController extends BaseEndpointController
{
    #[Route('/get-element', name: 'get_element', methods: ['GET'])]
    #[OA\Get(
        description: 'Method to get one single element by type and ID.',
        parameters: [
            new OA\Parameter(
                name: 'Authorization',
                description: 'Bearer (in Swagger UI use authorize feature to set header)',
                in: 'header'
            ),
            new OA\Parameter(
                name: 'config',
                description: 'Name of the config.',
                in: 'path',
                required: true,
                schema: new OA\Schema(
                    type: 'string'
                )
            ),
            new OA\Parameter(
                name: 'type',
                description: 'Type of elements – asset or object.',
                in: 'query',
                required: true,
                schema: new OA\Schema(
                    type: 'string',
                    enum: ['asset', 'object']
                )
            ),
            new OA\Parameter(
                name: 'id',
                description: 'ID of element.',
                in: 'query',
                required: true,
                schema: new OA\Schema(
                    type: 'integer'
                )
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'total_count',
                            description: 'Total count of available results.',
                            type: 'integer'
                        ),
                        new OA\Property(
                            property: 'items',
                            type: 'array',
                            items: new OA\Items()
                        ),
                        new OA\Property(
                            property: 'page_cursor',
                            description: 'Page cursor for next page.',
                            type: 'string'
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Not found'
            ),
            new OA\Response(
                response: 401,
                description: 'Access denied'
            ),
            new OA\Response(
                response: 500,
                description: 'Server error'
            ),
        ],
    )]
    public function getElementAction(IndexManager $indexManager, IndexQueryService $indexService): JsonResponse
    {
        $configuration = $this->getDataHubConfiguration();
        // Check if request is authenticated properly
        $this->authManager->checkAuthentication();
        $reader = new ConfigReader($configuration->getConfiguration());
        $id = $this->request->get('id');
        $type = $this->request->get('type');
        // Check if required parameters are missing
        $this->checkRequiredParameters(['id' => $id, 'type' => $type]);

        $root = Service::getElementById($type, $id);
        if (!$root->isAllowed('view', $this->user)) {
            throw new AccessDeniedHttpException('Missing the permission to list in the folder: '.$root->getRealFullPath());
        }

        $indices = [];

        if ('asset' === $type && $reader->isAssetIndexingEnabled()) {
            $indices = [
                $indexManager->getIndexName(IndexManager::INDEX_ASSET, $this->config),
                $indexManager->getIndexName(IndexManager::INDEX_ASSET_FOLDER, $this->config),
            ];
        } elseif ('object' === $type && $reader->isObjectIndexingEnabled()) {
            $indices = [$indexManager->getIndexName(IndexManager::INDEX_OBJECT_FOLDER, $this->config), ...array_map(fn ($className): string => $indexManager->getIndexName(mb_strtolower($className), $this->config), $reader->getObjectClassNames())];
        }

        foreach ($indices as $index) {
            try {
                $result = $indexService->get($id, $index);
            } catch (\Exception) {
                $result = [];
            }

            if (isset($result['found']) && true === $result['found']) {
                break;
            }
        }

        if ([] === $result || false === $result['found']) {
            throw new AssetNotFoundException(sprintf('Element with type \'%s\' and ID \'%s\' not found.', $type, $id));
        }

        return $this->json($this->buildResponse($result, $reader));
    }

    #[Route('/version', name: 'version', methods: ['GET'])]
    public function getElementVersion(): Response
    {
        $id = $this->request->query->getInt('id');
        $this->request->query->get('type', 'asset');

        $version = Version::getById($id);
        $asset = $version?->loadData();
        if (!$asset instanceof Asset) {
            return new JsonResponse(['success' => false, 'message' => "asset doesn't exist"], 404);
        }
        $response = [];

        if ($asset->isAllowed('versions', $this->user) && $version instanceof Version) {
            $response = [
                'assetId' => $asset->getId(),
                'metadata' => $asset->getMetadata(),
            ];
        }

        return new JsonResponse(['success' => true, 'data' => $response]);
    }

    #[Route('/versions', name: 'versions', methods: ['GET'])]
    public function getVersions(): Response
    {
        $assetId = $this->request->query->getInt('id');
        $type = $this->request->query->get('type', 'asset');

        $asset = Asset::getById($assetId);
        if (!$asset instanceof Asset) {
            return new JsonResponse(['success' => false, 'message' => "asset doesn't exist"], 404);
        }

        if ($asset->isAllowed('versions', $this->user)) {
            $schedule = $asset->getScheduledTasks();
            $schedules = [];
            foreach ($schedule as $task) {
                if ($task->getActive()) {
                    $schedules[$task->getVersion()] = $task->getDate();
                }
            }

            // only load auto-save versions from current user
            $list = new Listing();
            $list->setLoadAutoSave(true);
            $list->setCondition('cid = ? AND ctype = ? AND (autoSave=0 OR (autoSave=1 AND userId = ?)) ', [
                $asset->getId(),
                Service::getElementType($asset),
                $this->user->getId(),
            ])
                ->setOrderKey('date')
                ->setOrder('ASC');

            $versions = $list->load();
            $versions = Service::getSafeVersionInfo($versions);
            $versions = array_reverse($versions); // reverse array to sort by ID DESC
            foreach ($versions as &$version) {
                if (0 === $version['index']
                    && $version['date'] == $asset->getModificationDate()
                    && $version['versionCount'] == $asset->getVersionCount()
                ) {
                    $version['public'] = true;
                }
                $version['scheduled'] = null;
                if (\array_key_exists($version['id'], $schedules)) {
                    $version['scheduled'] = $schedules[$version['id']];
                }
            }

            return $this->json($versions);
        } else {
            throw $this->createAccessDeniedException('Permission denied, '.$type.' id ['.$assetId.']');
        }
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    #[Route('/lock-asset', name: 'lock_asset', methods: ['POST'])]
    #[OA\Post(
        description: 'Method to lock single element by type and ID.',
        parameters: [
            new OA\Parameter(
                name: 'Authorization',
                description: 'Bearer (in Swagger UI use authorize feature to set header)',
                in: 'header'
            ),
            new OA\Parameter(
                name: 'config',
                description: 'Name of the config.',
                in: 'path',
                required: true,
                schema: new OA\Schema(
                    type: 'string'
                )
            ),
            new OA\Parameter(
                name: 'type',
                description: 'Type of elements – asset or object.',
                in: 'query',
                required: true,
                schema: new OA\Schema(
                    type: 'string',
                    enum: ['asset', 'object']
                )
            ),
            new OA\Parameter(
                name: 'id',
                description: 'ID of element.',
                in: 'query',
                required: true,
                schema: new OA\Schema(
                    type: 'integer'
                )
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'total_count',
                            description: 'Total count of available results.',
                            type: 'integer'
                        ),
                        new OA\Property(
                            property: 'items',
                            type: 'array',
                            items: new OA\Items()
                        ),
                        new OA\Property(
                            property: 'page_cursor',
                            description: 'Page cursor for next page.',
                            type: 'string'
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Not found'
            ),
            new OA\Response(
                response: 401,
                description: 'Access denied'
            ),
            new OA\Response(
                response: 500,
                description: 'Server error'
            ),
        ],
    )]
    public function lock(AssetHelper $assetHelper): Response
    {
        $assetId = $this->request->query->getInt('id');
        $type = $this->request->query->get('type');

        $asset = Asset::getById($assetId);
        if (!$asset instanceof Asset) {
            return new JsonResponse(['success' => false, 'message' => "asset doesn't exist"], 404);
        }

        // check for lock on non-folder items only.
        if ('folder' !== $type && ($asset->isAllowed('publish', $this->user) || $asset->isAllowed('delete', $this->user))) {
            if ($assetHelper->isLocked($assetId, 'asset', $this->user->getId())) {
                return new JsonResponse(['success' => false, 'message' => 'asset is already locked for editing'], 403);
            }

            $assetHelper->lock($assetId, 'asset', $this->user->getId());

            return new JsonResponse(['success' => true, 'message' => 'asset was just locked']);
        }

        throw new AccessDeniedHttpException('Missing the permission to create new assets in the folder: '.$asset->getParent()->getRealFullPath());
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    #[Route('/unlock-asset', name: 'unlock_asset', methods: ['POST'])]
    #[OA\Post(
        description: 'Method to unlock single element by type and ID.',
        parameters: [
            new OA\Parameter(
                name: 'Authorization',
                description: 'Bearer (in Swagger UI use authorize feature to set header)',
                in: 'header'
            ),
            new OA\Parameter(
                name: 'config',
                description: 'Name of the config.',
                in: 'path',
                required: true,
                schema: new OA\Schema(
                    type: 'string'
                )
            ),
            new OA\Parameter(
                name: 'type',
                description: 'Type of elements – asset or object.',
                in: 'query',
                required: true,
                schema: new OA\Schema(
                    type: 'string',
                    enum: ['asset', 'object']
                )
            ),
            new OA\Parameter(
                name: 'id',
                description: 'ID of element.',
                in: 'query',
                required: true,
                schema: new OA\Schema(
                    type: 'integer'
                )
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'total_count',
                            description: 'Total count of available results.',
                            type: 'integer'
                        ),
                        new OA\Property(
                            property: 'items',
                            type: 'array',
                            items: new OA\Items()
                        ),
                        new OA\Property(
                            property: 'page_cursor',
                            type: 'string',
                            description: 'Page cursor for next page.'
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Not found'
            ),
            new OA\Response(
                response: 401,
                description: 'Access denied'
            ),
            new OA\Response(
                response: 500,
                description: 'Server error'
            ),
        ],
    )]
    public function unlock(AssetHelper $assetHelper): Response
    {
        $assetId = $this->request->query->getInt('id');
        $type = $this->request->query->get('type');

        $asset = Asset::getById($assetId);
        if (!$asset instanceof Asset) {
            return new JsonResponse(['success' => false, 'message' => "asset doesn't exist"], 404);
        }

        // check for lock on non-folder items only.
        if ('folder' !== $type && ($asset->isAllowed('publish', $this->user) || $asset->isAllowed('delete', $this->user))) {
            if ($assetHelper->isLocked($assetId, 'asset', $this->user->getId())) {
                $unlocked = $assetHelper->unlockForLocker($this->user->getId(), $assetId);
                if ($unlocked) {
                    return new JsonResponse(['success' => true, 'message' => 'asset has been unlocked for editing']);
                }

                return new JsonResponse(['success' => true, 'message' => 'asset is locked for editing'], 403);
            }

            return new JsonResponse(['success' => false, 'message' => 'asset is already unlocked for editing']);
        }

        throw new AccessDeniedHttpException('Missing the permission to create new assets in the folder: '.$asset->getParent()->getRealFullPath());
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    #[Route('/download-asset', name: 'download_asset', methods: ['GET'])]
    #[OA\Post(
        description: 'Method to download binary file by asset ID.',
        parameters: [
            new OA\Parameter(
                name: 'Authorization',
                description: 'Bearer (in Swagger UI use authorize feature to set header)',
                in: 'header'
            ),
            new OA\Parameter(
                name: 'config',
                description: 'Name of the config.',
                in: 'path',
                required: true,
                schema: new OA\Schema(
                    type: 'string'
                )
            ),
            new OA\Parameter(
                name: 'id',
                description: 'ID of element.',
                in: 'query',
                required: true,
                schema: new OA\Schema(
                    type: 'integer'
                )
            ),
            new OA\Parameter(
                name: 'thumbnail',
                description: 'Thumbnail config nae',
                in: 'query',
                required: true,
                schema: new OA\Schema(
                    type: 'string'
                )
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation.',
                content: new OA\MediaType()
            ),
            new OA\Response(
                response: 400,
                description: 'Not found'
            ),
            new OA\Response(
                response: 401,
                description: 'Access denied'
            ),
            new OA\Response(
                response: 500,
                description: 'Server error'
            ),
        ],
    )]
    public function download(): Response
    {
        $crossOriginHeaders = [
            'Allow' => 'GET, OPTIONS',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'authorization',
        ];

        // Send empty response for OPTIONS requests
        if ($this->request->isMethod('OPTIONS')) {
            return new Response('', 204, $crossOriginHeaders);
        }

        // Check if request is authenticated properly
        $this->authManager->checkAuthentication();
        $configuration = $this->getDataHubConfiguration();
        $reader = new ConfigReader($configuration->getConfiguration());

        $id = $this->request->get('id');

        // Check if required parameters are missing
        $this->checkRequiredParameters(['id' => $id]);

        $asset = Asset::getById($id);

        if (!$asset instanceof Asset) {
            throw new AssetNotFoundException(sprintf('Element with ID \'%s\' not found.', $id));
        }

        $thumbnail = $this->request->get('thumbnail');
        $defaultPreviewThumbnail = $this->getParameter('pimcore_ci_hub_adapter.default_preview_thumbnail');

        if (!empty($thumbnail) && ($asset instanceof Asset\Image || $asset instanceof Asset\Document)) {
            if (AssetProvider::CIHUB_PREVIEW_THUMBNAIL === $thumbnail && 'ciHub' === $reader->getType()) {
                if ($asset instanceof Asset\Image) {
                    $assetFile = $asset->getThumbnail($defaultPreviewThumbnail);
                } else {
                    $assetFile = $asset->getImageThumbnail($defaultPreviewThumbnail);
                }
            } elseif ($asset instanceof Asset\Image) {
                $assetFile = $asset->getThumbnail($thumbnail);
            } else {
                $assetFile = $asset->getImageThumbnail($thumbnail);
            }
        } else {
            $assetFile = $asset;
        }

        $response = new StreamedResponse();
        $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, basename($assetFile->getPath()));
        $response->headers->set('Content-Type', $assetFile->getMimetype());
        $response->headers->set('Content-Length', $assetFile->getFileSize());

        $stream = $assetFile->getStream();

        return $response->setCallback(function () use ($stream): void {
            fpassthru($stream);
        });
    }
}
