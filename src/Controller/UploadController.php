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

use CIHub\Bundle\SimpleRESTAdapterBundle\Exception\InvalidParameterException;
use CIHub\Bundle\SimpleRESTAdapterBundle\Helper\AssetHelper;
use CIHub\Bundle\SimpleRESTAdapterBundle\Helper\UploadHelper;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Attributes as OA;
use Pimcore\Config;
use Pimcore\Event\AssetEvents;
use Pimcore\Event\Model\Asset\ResolveUploadTargetEvent;
use Pimcore\Model\Asset;
use Pimcore\Model\Element\Service;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(path: ['/datahub/rest/{config}/asset', '/pimcore-datahub-webservices/simplerest/{config}/asset'], name: 'datahub_rest_endpoints_asset_')]
#[Security(name: 'Bearer')]
class UploadController extends BaseEndpointController
{
    public const PART_SIZE = 1024 * 1024;

    #[OA\Post(
        description: 'Simple method to create and upload asset',
        summary: 'Add asset',
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
            new OA\RequestBody(
                content: new OA\MediaType(
                    mediaType: 'multipart/form-data',
                    schema: new OA\Schema(
                        properties: [
                            new OA\Property(
                                property: 'file',
                                type: 'string',
                                format: 'binary'
                            ),
                        ],
                        type: 'file'
                    )
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
                            description: 'Asset ID',
                            type: 'integer'
                        ),
                        new OA\Property(
                            property: 'path',
                            description: 'Asset path',
                            type: 'string'
                        ),
                        new OA\Property(
                            property: 'success',
                            description: 'Succes response',
                            type: 'boolean'
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
    #[OA\Tag(name: 'Uploads')]
    #[Route('/add-asset', name: 'upload_asset', methods: ['POST'])]
    public function add(
        Config $pimcoreConfig,
        TranslatorInterface $translator,
        AssetHelper $assetHelper
    ): Response {
        try {
            $defaultUploadPath = $pimcoreConfig['assets']['default_upload_path'] ?? '/';

            /** @var UploadedFile $uploadedFile */
            $uploadedFile = $this->request->files->get('file');
            $sourcePath = $uploadedFile->getRealPath();
            $filename = $uploadedFile->getClientOriginalName();
            $filename = Service::getValidKey($filename, 'asset');

            if ('' === $filename) {
                throw new \Exception('The filename of the asset is empty');
            }

            if ($this->request->query->has('parentId')) {
                $parentAsset = Asset::getById((int) $this->request->query->get('parentId'));
                if (!$parentAsset instanceof Asset) {
                    throw new \Exception('Parent does not exist');
                }
                $parentId = $parentAsset->getId();
            } else {
                $parentId = Asset\Service::createFolderByPath($defaultUploadPath)->getId();
                $parentAsset = Asset::getById($parentId);
            }

            $context = $this->request->get('context');
            if ($context) {
                $context = json_decode($context, true, 512, \JSON_THROW_ON_ERROR);
                $context = $context ?: [];

                $assetHelper->validateManyToManyRelationAssetType($context, $filename, $sourcePath);

                $event = new ResolveUploadTargetEvent($parentId, $filename, $context);
                \Pimcore::getEventDispatcher()->dispatch($event, AssetEvents::RESOLVE_UPLOAD_TARGET);
                $filename = Service::getValidKey($event->getFilename(), 'asset');
                $parentId = $event->getParentId();
                $parentAsset = Asset::getById($parentId);
            }

            if (is_file($sourcePath) && filesize($sourcePath) < 1) {
                throw new \Exception('File is empty!');
            } elseif (!is_file($sourcePath)) {
                throw new \Exception('Something went wrong, please check upload_max_filesize and post_max_size in your php.ini as well as the write permissions of your temporary directories.');
            }

            if ($this->request->query->has('id')) {
                $asset = Asset::getById((int) $this->request->get('id'));

                return $assetHelper->updateAsset($asset, $sourcePath, $filename, $this->user, $translator);
            } elseif (Asset\Service::pathExists($parentAsset->getRealFullPath().'/'.$filename)) {
                $asset = Asset::getByPath($parentAsset->getRealFullPath().'/'.$filename);

                return $assetHelper->updateAsset($asset, $sourcePath, $filename, $this->user, $translator);
            } else {
                if (!$parentAsset->isAllowed('create', $this->user) && !$this->authManager->isAllowed($parentAsset, 'create', $this->user)) {
                    throw new AccessDeniedHttpException('Missing the permission to create new assets in the folder: '.$parentAsset->getRealFullPath());
                }
                $asset = Asset::create($parentAsset->getId(), [
                    'filename' => $filename,
                    'sourcePath' => $sourcePath,
                    'userOwner' => $this->user->getId(),
                    'userModification' => $this->user->getId(),
                ]);
            }

            @unlink($sourcePath);

            return new JsonResponse([
                'success' => true,
                'asset' => [
                    'id' => $asset->getId(),
                    'path' => $asset->getFullPath(),
                    'type' => $asset->getType(),
                ],
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @throws \Exception
     */
    #[OA\Post(
        description: 'Creates an upload session for a new file.',
        summary: 'Create upload session',
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
                name: 'file_name',
                description: 'The name of new file',
                in: 'query',
                required: true,
                schema: new OA\Schema(
                    type: 'string'
                ),
                example: 'Newfile.pdf'
            ),
            new OA\Parameter(
                name: 'filesize',
                description: 'The size of new file',
                in: 'query',
                required: true,
                schema: new OA\Schema(
                    type: 'integer'
                ),
                example: '104857600'
            ),
            new OA\Parameter(
                name: 'folder_id',
                description: 'The size of new file',
                in: 'query',
                required: true,
                schema: new OA\Schema(
                    type: 'integer'
                ),
                example: '0'
            ),
        ],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Returns a new upload session.'
            ),
        ],
    )]
    #[OA\Tag(name: 'Uploads (Chunked)')]
    #[Route('/upload/start', name: 'upload_start', methods: ['POST'])]
    public function start(UploadHelper $helper): Response
    {
        $this->request->get('filesize');
        $session = $helper->createSession($this->request, self::PART_SIZE);

        return new JsonResponse($helper->getSessionResponse(
            $this->request,
            $session->getId(),
            $this->config,
            self::PART_SIZE,
            0,
            $session->getTotalParts()
        ));
    }

    #[OA\Get(
        description: 'Return information about an upload session.',
        summary: 'Get upload session',
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
                ),
                example: 'cihub'
            ),
            new OA\Parameter(
                name: 'id',
                description: 'The ID of the upload session.',
                in: 'path',
                required: true,
                schema: new OA\Schema(
                    type: 'string'
                ),
                example: '01HAPEWC2QAD29AMJC9RM17CAH'
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Returns an upload session object.'
            ),
        ],
    )]
    #[OA\Tag(name: 'Uploads (Chunked)')]
    #[Route('/upload/start', name: 'upload_start_get', methods: ['GET'])]
    public function startGet(UploadHelper $helper): Response
    {
        $id = $this->request->get('id');
        $this->checkRequiredParameters(['id' => $id]);
        if ($helper->hasSession($id)) {
            $session = $helper->getSession($id);
            $response = $helper->getSessionResponse(
                $this->request,
                $id,
                $this->config,
                self::PART_SIZE,
                $session->getTotalParts(),
                $session->getPartsCount()
            );

            return new JsonResponse($response);
        }

        return new JsonResponse([], 404);
    }

    #[OA\Delete(
        description: 'Abort an upload session and discard all data uploaded.',
        summary: 'Remove upload session',
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
                ),
                example: 'cihub'
            ),
            new OA\Parameter(
                name: 'id',
                description: 'The ID of the upload session.',
                in: 'path',
                required: true,
                schema: new OA\Schema(
                    type: 'string'
                ),
                example: '01HAPEWC2QAD29AMJC9RM17CAH'
            ),
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: 'A blank response is returned if the session was successfully aborted.'
            ),
        ],
    )]
    #[OA\Tag(name: 'Uploads (Chunked)')]
    #[Route('/{id}', name: 'upload_abort', methods: ['DELETE'])]
    public function abort(string $id, UploadHelper $helper): Response
    {
        $helper->deleteSession($id);

        return new JsonResponse([], 204);
    }

    #[OA\Post(
        description: 'Close an upload session and create a file from the uploaded chunks.',
        summary: 'Commit upload session',
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
                ),
                example: 'cihub'
            ),
            new OA\Parameter(
                name: 'id',
                description: 'The ID of the upload session.',
                in: 'path',
                required: true,
                schema: new OA\Schema(
                    type: 'string'
                ),
                example: '01HAPEWC2QAD29AMJC9RM17CAH'
            ),
            new OA\Parameter(
                name: 'assetId',
                description: 'The ID of the asset.',
                in: 'query',
                required: true,
                schema: new OA\Schema(
                    type: 'integer'
                ),
                example: '0'
            ),
            new OA\Parameter(
                name: 'parts',
                description: 'The list details for the uploaded parts',
                in: 'query',
                required: true,
                schema: new OA\Schema(
                    type: 'array',
                    items: new OA\Items()
                )
            ),
        ],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Returns a new upload session.'
            ),
        ],
    )]
    #[OA\Tag(name: 'Uploads (Chunked)')]
    #[Route('/{id}/commit', name: 'upload_commit', methods: ['POST'])]
    public function commit(string $id, UploadHelper $helper): Response
    {
        return new JsonResponse($helper->commitSession($id));
    }

    #[OA\Get(
        description: 'Return a list of the chunks uploaded to the upload session so far.',
        summary: 'List parts',
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
                ),
                example: 'cihub'
            ),
            new OA\Parameter(
                name: 'id',
                description: 'The ID of the upload session.',
                in: 'path',
                required: true,
                schema: new OA\Schema(
                    type: 'string'
                ),
                example: '01HAPEWC2QAD29AMJC9RM17CAH'
            ),
        ],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Returns a new upload session.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'entries',
                            description: 'Parts array',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(
                                        property: 'id',
                                        description: 'Part ID',
                                        type: 'string'
                                    ),
                                    new OA\Property(
                                        property: 'checksum',
                                        description: 'Checksum of the part',
                                        type: 'string'
                                    ),
                                    new OA\Property(
                                        property: 'size',
                                        description: 'Size of the part',
                                        type: 'integer'
                                    ),
                                    new OA\Property(
                                        property: 'ordinal',
                                        description: 'Ordinal number of the part',
                                        type: 'integer'
                                    ),
                                ],
                                type: 'object'
                            )
                        ),
                        new OA\Property(
                            property: 'total',
                            description: 'Total uploaded parts',
                            type: 'integer'
                        ),
                    ],
                    type: 'object',
                    example: [
                        'entries' => [
                            [
                                'id' => '01HAPEWC2QAD29AMJC9RM17CAH',
                                'checksum' => '672dbdbcf8a83ebdf9225ef6f920bb0b5b3bc7fa8f73078e3a1d0',
                                'size' => 4_857_600,
                                'ordinal' => 1,
                            ],
                            [
                                'id' => '01HAPEWC2QAD29AMJC9RM1723A',
                                'checksum' => '6eb3746e6273a5c4e656bef1536e6cec36efe53fa1d010d548942',
                                'size' => 7_857_600,
                                'ordinal' => 2,
                            ],
                        ],
                        'total' => 2,
                    ]
                )
            ),
        ],
    )]
    #[OA\Tag(name: 'Uploads (Chunked)')]
    #[Route('/{id}/parts', name: 'upload_list_parts', methods: ['GET'])]
    public function parts(string $id, UploadHelper $helper): Response
    {
        $session = $helper->getSession($id);

        return new JsonResponse([
            'entries' => $session->getParts()->toArray(),
            'total' => $session->getPartsCount(),
        ]);
    }

    #[OA\Put(
        description: 'Return the status of the upload.',
        summary: 'Upload part of file',
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
                ),
                example: 'cihub'
            ),
            new OA\Parameter(
                name: 'id',
                description: 'The ID of the upload session.',
                in: 'path',
                required: true,
                schema: new OA\Schema(
                    type: 'string'
                ),
                example: '01HAPEWC2QAD29AMJC9RM17CAH'
            ),
            new OA\Parameter(
                name: 'ordinal',
                description: 'The ordinal number of the upload part.',
                in: 'query',
                required: true,
                schema: new OA\Schema(
                    type: 'integer'
                ),
                example: '1'
            ),
            new OA\RequestBody(
                description: 'The binary content of the file part.',
                content: new OA\MediaType(
                    mediaType: 'application/octet-stream',
                )
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: ''
            ),
        ],
    )]
    #[OA\Tag(name: 'Uploads (Chunked)')]
    #[Route('/{id}/part', name: 'upload_part', methods: ['PUT'])]
    public function part(string $id, UploadHelper $helper): Response
    {
        $session = $helper->getSession($id);

        /**
         * @var resource $content
         */
        $content = $this->request->getContent(true);
        $size = (int) $this->request->headers->get('Content-Length', 0);
        $ordinal = (int) $this->request->get('ordinal');
        if (0 === $size) {
            throw new InvalidParameterException(['Content-Length']);
        }
        if (0 === $ordinal) {
            throw new InvalidParameterException(['ordinal']);
        }

        $part = $helper->uploadPart($session, $content, $size, $ordinal);

        return new JsonResponse([
            'part' => [
                'part_id' => $part->getId(),
                'checksum' => $part->getHash(),
                'size' => $part->getSize(),
            ],
        ]);
    }

    #[OA\Get(
        description: 'Return the status of the upload.',
        summary: 'Get upload status',
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
                ),
                example: 'cihub'
            ),
            new OA\Parameter(
                name: 'id',
                description: 'The ID of the upload session.',
                in: 'path',
                required: true,
                schema: new OA\Schema(
                    type: 'string'
                ),
                example: '01HAPEWC2QAD29AMJC9RM17CAH'
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: ''
            ),
        ],
    )]
    #[OA\Tag(name: 'Uploads (Chunked)')]
    #[Route('/{id}/status', name: 'upload_status', methods: ['GET'])]
    public function status(string $id, UploadHelper $helper): Response
    {
        if ($helper->hasSession($id)) {
            $session = $helper->getSession($id);
        }

        $response = $helper->getSessionResponse($this->request, $id, $this->config, self::PART_SIZE, $session->getPartsCount(), $session->getTotalParts());
        $response['file_size'] = $session->getFileSize();

        return new JsonResponse($response);
    }
}
