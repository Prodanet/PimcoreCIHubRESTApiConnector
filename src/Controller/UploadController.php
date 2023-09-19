<?php

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Controller;

use CIHub\Bundle\SimpleRESTAdapterBundle\Helper\AssetHelper;
use CIHub\Bundle\SimpleRESTAdapterBundle\Helper\UploadHelper;
use Exception;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Attributes as OA;
use Pimcore;
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
use Symfony\Component\Uid\Ulid;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(path: ["/datahub/rest/{config}/asset", "/pimcore-datahub-webservices/simplerest/{config}/asset"], name: "datahub_rest_endpoints_asset_")]
#[Security(name: "Bearer")]
class UploadController extends BaseEndpointController
{
    const PART_SIZE = 1024;

    #[OA\Post(
        description: 'Simple method to create and upload asset',
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
                    enum: ["asset", "object"]
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
                            )
                        ],
                        type: 'file'
                    )
                )
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation.',
                content: new OA\JsonContent (
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
                        )
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
            )
        ],
    )]
    #[OA\Tag(name: "Uploads")]
    #[Route("/add-asset", name: "upload_asset", methods: ["POST"])]
    public function add(Config              $pimcoreConfig,
                        TranslatorInterface $translator,
                        AssetHelper         $assetHelper): Response
    {
        try {
            $defaultUploadPath = $pimcoreConfig['assets']['default_upload_path'] ?? '/';

            /** @var UploadedFile $uploadedFile */
            $uploadedFile = $this->request->files->get('file');
            $sourcePath = $uploadedFile->getRealPath();
            $filename = $uploadedFile->getClientOriginalName();
            $filename = Service::getValidKey($filename, 'asset');

            if (empty($filename)) {
                throw new Exception('The filename of the asset is empty');
            }

            if ($this->request->query->has('parentId')) {
                $parentAsset = Asset::getById((int)$this->request->query->get('parentId'));
                if (!$parentAsset instanceof Asset) {
                    throw new Exception('Parent does not exist');
                }
                $parentId = $parentAsset->getId();
            } else {
                $parentId = Asset\Service::createFolderByPath($defaultUploadPath)->getId();
                $parentAsset = Asset::getById($parentId);
            }

            $context = $this->request->get('context');
            if ($context) {
                $context = json_decode($context, true);
                $context = $context ?: [];

                $assetHelper->validateManyToManyRelationAssetType($context, $filename, $sourcePath);

                $event = new ResolveUploadTargetEvent($parentId, $filename, $context);
                Pimcore::getEventDispatcher()->dispatch($event, AssetEvents::RESOLVE_UPLOAD_TARGET);
                $filename = Service::getValidKey($event->getFilename(), 'asset');
                $parentId = $event->getParentId();
                $parentAsset = Asset::getById($parentId);
            }

            if (is_file($sourcePath) && filesize($sourcePath) < 1) {
                throw new Exception('File is empty!');
            } elseif (!is_file($sourcePath)) {
                throw new Exception('Something went wrong, please check upload_max_filesize and post_max_size in your php.ini as well as the write permissions of your temporary directories.');
            }

            if ($this->request->query->has('id')) {
                $asset = Asset::getById((int)$this->request->get('id'));
                return $assetHelper->updateAsset($asset, $sourcePath, $filename, $this->user, $translator);
            } else if (Asset\Service::pathExists($parentAsset->getRealFullPath() . '/' . $filename)) {
                $asset = Asset::getByPath($parentAsset->getRealFullPath() . '/' . $filename);
                return $assetHelper->updateAsset($asset, $sourcePath, $filename, $this->user, $translator);
            } else {
                if (!$parentAsset->isAllowed('create', $this->user) && !$this->authManager->isAllowed($parentAsset, 'create', $this->user)) {
                    throw new AccessDeniedHttpException(
                        'Missing the permission to create new assets in the folder: ' . $parentAsset->getRealFullPath()
                    );
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
                ]
            ]);

        } catch (Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    #[OA\Post(
        description: 'Creates an upload session for a new file.',
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
                name: 'filename',
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
            )
        ],
    )]
    #[OA\Tag(name: "Uploads (Chunked)")]
    #[Route("/upload/start", name: "upload_start", methods: ["POST"])]
    public function start(UploadHelper $helper): Response
    {
        $uuid = new Ulid();
        $totalParts = (int)($this->request->get('filesize') / $partSize);
        $response = $helper->getSessionResponse($this->request, $uuid, $this->config, self::PART_SIZE, 0, $totalParts);
        $helper->createSession($uuid, [], (int)($this->request->get('filesize') / self::PART_SIZE));

        return new JsonResponse($response);
    }

    #[OA\Get(
        description: 'Return information about an upload session.',
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
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Returns an upload session object.'
            )
        ],
    )]
    #[OA\Tag(name: "Uploads (Chunked)")]
    #[Route("/upload/start", name: "upload_start_get", methods: ["GET"])]
    public function startGet(UploadHelper $helper): Response
    {
        $id = $this->request->get('id');
        $this->checkRequiredParameters(['id' => $id]);
        if ($helper->hasSession($id)) {
            $session = $helper->getSession($id);
            $response = $helper->getSessionResponse($this->request, $id, $this->config, self::PART_SIZE, 0, (int)$session['total_parts']);
            return new JsonResponse($response);
        }

        return new JsonResponse([], 404);
    }

    #[OA\Delete(
        description: 'Abort an upload session and discard all data uploaded.',
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
            )

        ],
        responses: [
            new OA\Response(
                response: 204,
                description: 'A blank response is returned if the session was successfully aborted.'
            )
        ],
    )]
    #[OA\Tag(name: "Uploads (Chunked)")]
    #[Route("/{id}", name: "upload_abort", methods: ["DELETE"])]
    public function abort(string $id, UploadHelper $helper): Response
    {
        $helper->deleteSession($id);
        return new JsonResponse([], 204);
    }

    #[OA\Post(
        description: 'Close an upload session and create a file from the uploaded chunks.',
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
            )
        ],
    )]
    #[OA\Tag(name: "Uploads (Chunked)")]
    #[Route("/{id}/commit", name: "upload_commit", methods: ["POST"])]
    public function commit(string $id): Response
    {
        $helper->deleteSession($id);
        $asset = Asset::getById($this->request->get('assetId'));
        if ($asset instanceof Asset) {
            return new JsonResponse([$id]);
        }

        return new JsonResponse([] . 404);
    }

    #[OA\Get(
        description: 'Return a list of the chunks uploaded to the upload session so far.',
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
            )
        ],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Returns a new upload session.'
            )
        ],
    )]
    #[OA\Tag(name: "Uploads (Chunked)")]
    #[Route("/{id}/parts", name: "upload_list_parts", methods: ["GET"])]
    public function parts(string $id, UploadHelper $helper): Response
    {
        return new JsonResponse($helper->getParts($id));
    }

    #[OA\Put(
        description: 'Return the status of the upload.',
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
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: ''
            )
        ],
    )]
    #[OA\Tag(name: "Uploads (Chunked)")]
    #[Route("/{id}/part", name: "upload_part", methods: ["PUT"])]
    public function part(string $id): Response
    {
        $uuid = new Ulid();

        return new JsonResponse([
            "part" => [
                "part_id" => $uuid,
                "sha1" => "134b65991ed521fcfe4724b7d814ab8ded5185dc",
                "size" => 3222784
            ]
        ]);
    }

    #[OA\Get(
        description: 'Return the status of the upload.',
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
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: ''
            )
        ],
    )]
    #[OA\Tag(name: "Uploads (Chunked)")]
    #[Route("/{id}/status", name: "upload_status", methods: ["GET"])]
    public function status(string $id, UploadHelper $helper): Response
    {
        if ($helper->hasSession($id)) {
            $data = $helper->getSession($id);
            $response = $helper->getSessionResponse($this->request, $id, $this->config, self::PART_SIZE, count($data['parts']), (int)$data['total_parts']);
            $response['file_size'] = $data['file_size'];
            return new JsonResponse($response);
        }

        return new JsonResponse([], 404);
    }

}
