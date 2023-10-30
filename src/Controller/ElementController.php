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

use CIHub\Bundle\SimpleRESTAdapterBundle\Helper\AssetHelper;
use CIHub\Bundle\SimpleRESTAdapterBundle\Messenger\UpdateIndexElementMessage;
use CIHub\Bundle\SimpleRESTAdapterBundle\Traits\RestHelperTrait;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Attributes as OA;
use Pimcore\Model\Asset;
use Pimcore\Model\Element\Service;
use Pimcore\Model\Element\Tag;
use Pimcore\Model\Version\Listing;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
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
                            property: 'id',
                            description: 'Element ID',
                            type: 'integer'
                        ),
                        new OA\Property(
                            property: 'Parent ID',
                            description: 'Parent ID',
                            type: 'integer',
                        ),
                        new OA\Property(
                            property: 'name',
                            description: 'Element name',
                            type: 'string'
                        ),
                        new OA\Property(
                            property: 'type',
                            description: 'Type of element',
                            type: 'string'
                        ),
                        new OA\Property(
                            property: 'locked',
                            description: 'Element is locked?',
                            type: 'boolean'
                        ),
                        new OA\Property(
                            property: 'tags',
                            description: 'Tags assigned to element',
                            type: 'array',
                            items: new OA\Items(type: 'string'),
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
    public function getElementAction(AssetHelper $assetHelper): JsonResponse
    {
        $this->authManager->checkAuthentication();
        $element = $this->getElementByIdType();
        $elementType = $element instanceof Asset ? 'asset' : 'object';
        if (!$element->isAllowed('view', $this->user)) {
            throw new AccessDeniedHttpException('Missing the permission to list in the folder: '.$element->getRealFullPath());
        }
        $tags = Tag::getTagsForElement('asset', $element->getId());

        return $this->json([
            'id' => $element->getId(),
            'parentId' => $element->getParentId(),
            'name' => $element->getKey(),
            'type' => $element->getType(),
            'locked' => $assetHelper->isLocked($element->getId(), $elementType, $this->user->getId()),
            'tags' => array_map(function (Tag $tag) {
                return [
                    'label' => $tag->getName(),
                ];
            }, $tags),
        ]);
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
    public function lock(AssetHelper $assetHelper, MessageBusInterface $messageBus): Response
    {
        $element = $this->getElementByIdType();
        $elementType = $element instanceof Asset ? 'asset' : 'object';
        if ('folder' !== $element->getType()
            && ($element->isAllowed('publish', $this->user)
                || $element->isAllowed('delete', $this->user))
        ) {
            if ($assetHelper->isLocked($element->getId(), $elementType, $this->user->getId())) {
                return new JsonResponse(['success' => false, 'message' => $elementType.' with id ['.$element->getId().'] is already locked for editing'], 403);
            }

            $assetHelper->lock($element->getId(), $elementType, $this->user->getId());
            $messageBus->dispatch(new UpdateIndexElementMessage($element->getId(), $elementType, $this->request->get('config')));

            return new JsonResponse(['success' => true, 'message' => $elementType.' with id ['.$element->getId().'] was just locked']);
        }

        throw new AccessDeniedHttpException('Missing the permission to create new '.$elementType.' in the folder: '.$element->getParent()->getRealFullPath());
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
    public function unlock(AssetHelper $assetHelper, MessageBusInterface $messageBus): Response
    {
        $element = $this->getElementByIdType();
        $elementType = $element instanceof Asset ? 'asset' : 'object';
        // check for lock on non-folder items only.
        if ('folder' !== $element->getType() && ($element->isAllowed('publish', $this->user) || $element->isAllowed('delete', $this->user))) {
            if ($assetHelper->isLocked($element->getId(), $elementType, $this->user->getId())) {
                $unlocked = $assetHelper->unlockForLocker($this->user->getId(), $element->getId());
                if ($unlocked) {
                    return new JsonResponse(['success' => true, 'message' => $elementType.' with id ['.$element->getId().'] has been unlocked for editing']);
                }
                $messageBus->dispatch(new UpdateIndexElementMessage($element->getId(), $elementType, $this->request->get('config')));

                return new JsonResponse(['success' => true, 'message' => $elementType.' with id ['.$element->getId().'] is locked for editing'], 403);
            }

            return new JsonResponse(['success' => false, 'message' => $elementType.' with id ['.$element->getId().'] is already unlocked for editing']);
        }

        throw new AccessDeniedHttpException('Missing the permission to create new '.$elementType.' in the folder: '.$element->getParent()->getRealFullPath());
    }
}
