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
use CIHub\Bundle\SimpleRESTAdapterBundle\Provider\AssetProvider;
use CIHub\Bundle\SimpleRESTAdapterBundle\Reader\ConfigReader;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Attributes as OA;
use Pimcore\Config;
use Pimcore\Model\Asset;
use Pimcore\Model\Asset\Document;
use Pimcore\Model\Asset\Image;
use Pimcore\Model\Element\Service;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(path: ['/datahub/rest/{config}/asset', '/pimcore-datahub-webservices/simplerest/{config}'], name: 'datahub_rest_endpoints_asset_')]
#[Security(name: 'Bearer')]
#[OA\Tag(name: 'Asset')]
final class AssetController extends BaseEndpointController
{
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
            new OA\Parameter(
                name: 'parentId',
                description: 'Parent ID of element.',
                in: 'query',
                required: false,
                schema: new OA\Schema(
                    type: 'integer'
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
    #[OA\Tag(name: 'Asset')]
    #[Route('', name: 'upload', methods: ['POST'])]
    public function add(
        Config $pimcoreConfig
    ): Response {
        $parentId = $this->request->query->getInt('parentId');
        $this->checkRequiredParameters(['parentId' => $parentId]);
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

            $parentAsset = Asset::getById($this->request->query->getInt('parentId'));
            if (!$parentAsset instanceof Asset) {
                throw new \Exception('Parent does not exist');
            }

            if (is_file($sourcePath) && filesize($sourcePath) < 1) {
                throw new \Exception('File is empty!');
            } elseif (!is_file($sourcePath)) {
                throw new \Exception('Something went wrong, please check upload_max_filesize and post_max_size in your php.ini as well as the write permissions of your temporary directories.');
            }

            if (!$parentAsset->isAllowed('create', $this->user) && !$this->authManager->isAllowed($parentAsset, 'create', $this->user)) {
                throw new AccessDeniedHttpException('Missing the permission to create new assets in the folder: '.$parentAsset->getRealFullPath());
            }

            $asset = Asset::create($parentAsset->getId(), [
                'filename' => $filename,
                'sourcePath' => $sourcePath,
                'userOwner' => $this->user->getId(),
                'userModification' => $this->user->getId(),
            ]);

            @unlink($sourcePath);

            return new JsonResponse([
                'success' => true,
                'asset' => [
                    'id' => $asset->getId(),
                    'path' => $asset->getFullPath(),
                    'type' => $asset->getType(),
                ],
            ]);
        } catch (\Exception $exception) {
            return new JsonResponse([
                'success' => false,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    #[OA\Post(
        description: 'Simple method to update and upload asset',
        summary: 'Update asset',
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
                description: 'Element ID.',
                in: 'query',
                required: true,
                schema: new OA\Schema(
                    type: 'integer'
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
    #[OA\Tag(name: 'Asset')]
    #[Route('/update', name: 'update', methods: ['POST'])]
    public function update(
        TranslatorInterface $translator,
        AssetHelper $assetHelper
    ): Response {
        $id = $this->request->query->getInt('id');
        $this->checkRequiredParameters(['id' => $id]);

        try {
            $asset = Asset::getById($id);
            if ($asset instanceof Asset) {
                if ($asset->isAllowed('allowOverwrite', $this->user)) {
                    /** @var UploadedFile $uploadedFile */
                    $uploadedFile = $this->request->files->get('file');
                    $sourcePath = $uploadedFile->getRealPath();
                    $filename = $uploadedFile->getClientOriginalName();
                    $filename = Service::getValidKey($filename, 'asset');

                    if ('' === $filename) {
                        throw new \Exception('The filename of the asset is empty');
                    }

                    if (is_file($sourcePath) && filesize($sourcePath) < 1) {
                        throw new \Exception('File is empty!');
                    } elseif (!is_file($sourcePath)) {
                        throw new \Exception('Something went wrong, please check upload_max_filesize and post_max_size in your php.ini as well as the write permissions of your temporary directories.');
                    }

                    return $assetHelper->updateAsset($asset, $sourcePath, $filename, $this->user, $translator);
                } else {
                    throw new AccessDeniedHttpException('Missing the permission to overwrite asset: '.$asset->getId());
                }
            } else {
                throw new \Exception('Asset with id ['.$id."] doesn't exist");
            }
        } catch (\Exception $exception) {
            return new JsonResponse([
                'success' => false,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    #[Route('/download', name: 'download', methods: ['GET'])]
    #[OA\Get(
        description: 'Method to download binary file by asset ID.',
        summary: 'Download Asset',
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
        $configReader = new ConfigReader($configuration->getConfiguration());

        $id = $this->request->query->getInt('id');
        $this->request->query->set('type', 'asset');

        // Check if required parameters are missing
        $this->checkRequiredParameters(['id' => $id]);
        $element = $this->getElementByIdType();
        $thumbnail = $this->request->get('thumbnail');
        $defaultPreviewThumbnail = $this->getParameter('pimcore_ci_hub_adapter.default_preview_thumbnail');

        if (!empty($thumbnail) && ($element instanceof Image || $element instanceof Document)) {
            if (AssetProvider::CIHUB_PREVIEW_THUMBNAIL === $thumbnail && 'ciHub' === $configReader->getType()) {
                if ($element instanceof Image) {
                    $elementFile = $element->getThumbnail($defaultPreviewThumbnail);
                } else {
                    $elementFile = $element->getImageThumbnail($defaultPreviewThumbnail);
                }
            } elseif ($element instanceof Image) {
                $elementFile = $element->getThumbnail($thumbnail);
            } else {
                $elementFile = $element->getImageThumbnail($thumbnail);
            }
        } else {
            $elementFile = $element;
        }

        $streamedResponse = new StreamedResponse();
        $streamedResponse->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, basename($elementFile->getPath()));
        $streamedResponse->headers->set('Content-Type', $elementFile->getMimetype());
        $streamedResponse->headers->set('Content-Length', $elementFile->getFileSize());

        $stream = $elementFile->getStream();

        return $streamedResponse->setCallback(static function () use ($stream): void {
            fpassthru($stream);
        });
    }
}
