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
use CIHub\Bundle\SimpleRESTAdapterBundle\Reader\ConfigReader;
use CIHub\Bundle\SimpleRESTAdapterBundle\Traits\RestHelperTrait;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Attributes as OA;
use Pimcore\Model\Asset;
use Pimcore\Model\Element\Service;
use Pimcore\Model\Version\Listing;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: ['/datahub/rest/{config}/element', '/pimcore-datahub-webservices/simplerest/{config}'], name: 'datahub_rest_endpoints_element_')]
#[Security(name: 'Bearer')]
#[OA\Tag(name: 'Element')]
final class ElementController extends BaseEndpointController
{
    use RestHelperTrait;

    #[Route('', name: 'get', methods: ['GET'])]
    #[OA\Get(
        description: 'Method to get one single element by type and ID.',
        summary: 'Get Element (eg. Asset, Object)',
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
        $configReader = new ConfigReader($configuration->getConfiguration());

        $root = $this->getElementByIdType();
        if (!$root->isAllowed('view', $this->user)) {
            throw new AccessDeniedHttpException('Missing the permission to list in the folder: '.$root->getRealFullPath());
        }

        $indices = [];

        if ('asset' === $root->getType() && $configReader->isAssetIndexingEnabled()) {
            $indices = [
                $indexManager->getIndexName(IndexManager::INDEX_ASSET, $this->config),
                $indexManager->getIndexName(IndexManager::INDEX_ASSET_FOLDER, $this->config),
            ];
        } elseif ('object' === $root->getType() && $configReader->isObjectIndexingEnabled()) {
            $indices = [$indexManager->getIndexName(IndexManager::INDEX_OBJECT_FOLDER, $this->config), ...array_map(fn ($className): string => $indexManager->getIndexName(mb_strtolower($className), $this->config), $configReader->getObjectClassNames())];
        }

        $result = [];
        foreach ($indices as $index) {
            try {
                $result = $indexService->get($root->getId(), $index);
            } catch (\Exception) {
                $result = [];
            }

            if (isset($result['found']) && true === $result['found']) {
                break;
            }
        }

        if ([] === $result || false === $result['found']) {
            throw new AssetNotFoundException(sprintf("Element with type '%s' and ID '%s' not found.", $root->getType(), $root->getId()));
        }

        return $this->json($this->buildResponse($result, $configReader));
    }

    #[Route('', name: 'delete', methods: ['DELETE'])]
    #[OA\Delete(
        description: 'Method to delete a single element by type and ID.',
        summary: 'Delete Element (eg. Asset, Object)',
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
                            property: 'success',
                            description: 'Success status.',
                            type: 'boolean'
                        ),
                        new OA\Property(
                            property: 'message',
                            description: 'Message.',
                            type: 'string',
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
    public function delete(): Response
    {
        $element = $this->getElementByIdType();
        if ($element->isAllowed('delete', $this->user)) {
            $element->delete();

            return new JsonResponse([
                'success' => true,
                'message' => $element->getType().' in the folder: '.$element->getParent()->getRealFullPath().' was deleted',
            ]);
        }

        throw new AccessDeniedHttpException('Missing the permission to remove '.$element->getType().' in the folder: '.$element->getParent()->getRealFullPath());
    }

    #[Route('/version', name: 'version', methods: ['GET'])]
    #[OA\Get(
        description: 'Method to get a specified version of the element by type and ID.',
        summary: 'Get Version of Element (eg. Asset, Object)',
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
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'id',
                            description: 'Version ID',
                            type: 'integer'
                        ),
                        new OA\Property(
                            property: 'cid',
                            description: 'Asset ID',
                            type: 'integer'
                        ),
                        new OA\Property(
                            property: 'ctype',
                            description: 'Object type',
                            type: 'string'
                        ),
                        new OA\Property(
                            property: 'note',
                            description: 'Version note',
                            type: 'string'
                        ),
                        new OA\Property(
                            property: 'date',
                            description: 'Timestamp of version creation',
                            type: 'integer'
                        ),
                        new OA\Property(
                            property: 'public',
                            description: 'Version is public?',
                            type: 'boolean'
                        ),
                        new OA\Property(
                            property: 'versionCount',
                            description: 'Version sequence number',
                            type: 'integer'
                        ),
                        new OA\Property(
                            property: 'autoSave',
                            description: 'Version is auto-save?',
                            type: 'boolean'
                        ),
                        new OA\Property(
                            property: 'user',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(
                                        property: 'name',
                                        type: 'string'
                                    ),
                                    new OA\Property(
                                        property: 'id',
                                        type: 'integer'
                                    ),
                                ]
                            )
                        ),
                        new OA\Property(
                            property: 'metadata',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(
                                        property: 'data',
                                        type: 'string'
                                    ),
                                    new OA\Property(
                                        property: 'language',
                                        type: 'string',
                                        nullable: true
                                    ),
                                    new OA\Property(
                                        property: 'name',
                                        type: 'string'
                                    ),
                                    new OA\Property(
                                        property: 'type',
                                        type: 'string'
                                    ),
                                    new OA\Property(
                                        property: 'config',
                                        type: 'string',
                                        nullable: true
                                    ),
                                ]
                            )
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
    public function getElementVersion(): Response
    {
        [$element, $version] = $this->getVersion();
        $response = [];
        if ($element->isAllowed('versions', $this->user)) {
            $response = [
                'id' => $version->getId(),
                'cid' => $element->getId(),
                'note' => $version->getNote(),
                'date' => $version->getDate(),
                'public' => $version->isPublic(),
                'versionCount' => $version->getVersionCount(),
                'autoSave' => $version->isAutoSave(),
                'user' => [
                    'name' => $version->getUser()->getName(),
                    'id' => $version->getUser()->getId(),
                ],
            ];
            if ($element instanceof Asset) {
                $response['fileSize'] = $element->getMetadata();
            }
        }

        return new JsonResponse(['success' => true, 'data' => $response]);
    }

    #[Route('/versions', name: 'versions', methods: ['GET'])]
    #[OA\Get(
        description: 'Method to get all versions of the element by type and ID.',
        summary: 'Get all Versions of Element (eg. Asset, Object)',
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
                            property: 'id',
                            description: 'Version ID',
                            type: 'integer'
                        ),
                        new OA\Property(
                            property: 'cid',
                            description: 'Asset ID',
                            type: 'integer'
                        ),
                        new OA\Property(
                            property: 'ctype',
                            description: 'Object type',
                            type: 'string'
                        ),
                        new OA\Property(
                            property: 'note',
                            description: 'Version note',
                            type: 'string'
                        ),
                        new OA\Property(
                            property: 'date',
                            description: 'Timestamp of version creation',
                            type: 'integer'
                        ),
                        new OA\Property(
                            property: 'public',
                            description: 'Version is public?',
                            type: 'boolean'
                        ),
                        new OA\Property(
                            property: 'versionCount',
                            description: 'Version sequence number',
                            type: 'integer'
                        ),
                        new OA\Property(
                            property: 'autoSave',
                            description: 'Version is auto-save?',
                            type: 'boolean'
                        ),
                        new OA\Property(
                            property: 'user',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(
                                        property: 'name',
                                        type: 'string'
                                    ),
                                    new OA\Property(
                                        property: 'id',
                                        type: 'integer'
                                    ),
                                ]
                            )
                        ),
                        new OA\Property(
                            property: 'index',
                            type: 'integer',
                        ),
                        new OA\Property(
                            property: 'scheduled',
                            type: 'integer',
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
    public function getVersions(): Response
    {
        $element = $this->getElementByIdType();

        if ($element->isAllowed('versions', $this->user)) {
            $schedule = $element->getScheduledTasks();
            $schedules = [];
            foreach ($schedule as $task) {
                if ($task->getActive()) {
                    $schedules[$task->getVersion()] = $task->getDate();
                }
            }

            // only load auto-save versions from current user
            $listing = new Listing();
            $listing->setLoadAutoSave(true);
            $listing->setCondition('cid = ? AND ctype = ? AND (autoSave=0 OR (autoSave=1 AND userId = ?)) ', [
                $element->getId(),
                $element->getType(),
                $this->user->getId(),
            ])
                ->setOrderKey('date')
                ->setOrder('ASC');

            $versions = $listing->load();
            $versions = Service::getSafeVersionInfo($versions);
            $versions = array_reverse($versions); // reverse array to sort by ID DESC
            foreach ($versions as &$version) {
                if (0 === $version['index']
                    && $version['date'] == $element->getModificationDate()
                    && $version['versionCount'] == $element->getVersionCount()
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
            throw $this->createAccessDeniedException('Permission denied, '.$element->getType().' id ['.$element->getId().']');
        }
    }

    #[Route('/lock', name: 'lock', methods: ['POST'])]
    #[OA\Post(
        description: 'Method to lock single element by type and ID.',
        summary: 'Lock Asset',
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
                            property: 'success',
                            description: 'Success status.',
                            type: 'boolean'
                        ),
                        new OA\Property(
                            property: 'message',
                            description: 'Message.',
                            type: 'string',
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
        $element = $this->getElementByIdType();

        if ('folder' !== $element->getType()
            && ($element->isAllowed('publish', $this->user)
                || $element->isAllowed('delete', $this->user))
        ) {
            if ($assetHelper->isLocked($element->getId(), 'asset', $this->user->getId())) {
                return new JsonResponse(['success' => false, 'message' => $element->getType().' with id ['.$element->getId().'] is already locked for editing'], 403);
            }

            $assetHelper->lock($element->getId(), $element->getType(), $this->user->getId());

            return new JsonResponse(['success' => true, 'message' => $element->getType().' with id ['.$element->getId().'] was just locked']);
        }

        throw new AccessDeniedHttpException('Missing the permission to create new '.$element->getType().' in the folder: '.$element->getParent()->getRealFullPath());
    }

    #[Route('/unlock', name: 'unlock', methods: ['POST'])]
    #[OA\Post(
        description: 'Method to unlock single element by type and ID.',
        summary: 'Unlock Asset',
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
                            property: 'success',
                            description: 'Success status.',
                            type: 'boolean'
                        ),
                        new OA\Property(
                            property: 'message',
                            description: 'Message.',
                            type: 'string',
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
        $element = $this->getElementByIdType();

        // check for lock on non-folder items only.
        if ('folder' !== $element->getType() && ($element->isAllowed('publish', $this->user) || $element->isAllowed('delete', $this->user))) {
            if ($assetHelper->isLocked($element->getId(), 'asset', $this->user->getId())) {
                $unlocked = $assetHelper->unlockForLocker($this->user->getId(), $element->getId());
                if ($unlocked) {
                    return new JsonResponse(['success' => true, 'message' => $element->getType().' with id ['.$element->getId().'] has been unlocked for editing']);
                }

                return new JsonResponse(['success' => true, 'message' => $element->getType().' with id ['.$element->getId().'] is locked for editing'], 403);
            }

            return new JsonResponse(['success' => false, 'message' => $element->getType().' with id ['.$element->getId().'] is already unlocked for editing']);
        }

        throw new AccessDeniedHttpException('Missing the permission to create new '.$element->getType().' in the folder: '.$element->getParent()->getRealFullPath());
    }
}
