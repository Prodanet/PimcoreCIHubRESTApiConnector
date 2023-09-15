<?php

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Controller;

use CIHub\Bundle\SimpleRESTAdapterBundle\Elasticsearch\Index\IndexQueryService;
use CIHub\Bundle\SimpleRESTAdapterBundle\Exception\AssetNotFoundException;
use CIHub\Bundle\SimpleRESTAdapterBundle\Helper\AssetHelper;
use CIHub\Bundle\SimpleRESTAdapterBundle\Manager\IndexManager;
use CIHub\Bundle\SimpleRESTAdapterBundle\Provider\AssetProvider;
use CIHub\Bundle\SimpleRESTAdapterBundle\Reader\ConfigReader;
use Exception;
use Pimcore;
use Pimcore\Model\Element;
use Pimcore\Config;
use Pimcore\Event\AssetEvents;
use Pimcore\Event\Model\Asset\ResolveUploadTargetEvent;
use Pimcore\Model\Asset;
use Pimcore\Model\Element\Service;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;


/**
 * @Route("/pimcore-datahub-webservices/simplerest/{config}")
 */
class AssetController extends BaseEndpointController
{
    /**
     * @param IndexManager $indexManager
     * @param IndexQueryService $indexService
     *
     * @return JsonResponse
     * @throws \Doctrine\DBAL\Exception
     */
    #[Route("/get-element", name: "simple_rest_adapter_endpoints_get_element", methods: ["GET"])]
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
        if (!$root->isAllowed('view')) {
            throw new AccessDeniedHttpException(
                'Missing the permission to list in the folder: ' . $root->getRealFullPath()
            );
        }

        $indices = [];

        if ('asset' === $type && $reader->isAssetIndexingEnabled()) {
            $indices = [
                $indexManager->getIndexName(IndexManager::INDEX_ASSET, $this->config),
                $indexManager->getIndexName(IndexManager::INDEX_ASSET_FOLDER, $this->config),
            ];
        } elseif ('object' === $type && $reader->isObjectIndexingEnabled()) {
            $indices = array_merge(
                [$indexManager->getIndexName(IndexManager::INDEX_OBJECT_FOLDER, $this->config)],
                array_map(function ($className) use ($indexManager) {
                    return $indexManager->getIndexName(strtolower($className), $this->config);
                }, $reader->getObjectClassNames())
            );
        }

        foreach ($indices as $index) {
            try {
                $result = $indexService->get($id, $index);
            } catch (Exception $ignore) {
                $result = [];
            }

            if (isset($result['found']) && true === $result['found']) {
                break;
            }
        }

        if (empty($result) || false === $result['found']) {
            throw new AssetNotFoundException(sprintf('Element with type \'%s\' and ID \'%s\' not found.', $type, $id));
        }

        return $this->json($this->buildResponse($result, $reader));
    }

    /**
     * @return Response
     * @Route("/lock-asset", name="lock_asset", methods={"POST", "OPTIONS"})
     * @throws \Doctrine\DBAL\Exception
     */
    public function lock(): Response {
        $user = $this->authManager->authenticate();
        $assetId = $this->request->query->getInt('id');
        $type = $this->request->query->get('type');

        $asset = Asset::getById($assetId);
        if (!$asset instanceof Asset) {
            return new JsonResponse(['success' => false, 'message' => "asset doesn't exist"], 404);
        }

        // check for lock on non-folder items only.
        if ($type !== 'folder' && ($asset->isAllowed('publish', $user) || $asset->isAllowed('delete', $user))) {
            if (AssetHelper::isLocked($assetId, 'asset', $user->getId())) {
                return new JsonResponse(['success' => false, 'message' => "asset is already locked for editing"], 403);
            }

            AssetHelper::lock($assetId, 'asset', $user->getId());

            return new JsonResponse(['success' => true, 'message' => "asset was just locked"]);
        }

        throw new AccessDeniedHttpException(
            'Missing the permission to create new assets in the folder: ' . $asset->getParent()->getRealFullPath()
        );
    }

    /**
     * @return Response
     * @Route("/unlock-asset", name="unlock_asset", methods={"POST", "OPTIONS"})
     * @throws \Doctrine\DBAL\Exception
     */
    public function unlock(): Response {
        $user = $this->authManager->authenticate();
        $assetId = $this->request->query->getInt('id');
        $type = $this->request->query->get('type');

        $asset = Asset::getById($assetId);
        if (!$asset instanceof Asset) {
            return new JsonResponse(['success' => false, 'message' => "asset doesn't exist"], 404);
        }

        // check for lock on non-folder items only.
        if ($type !== 'folder' && ($asset->isAllowed('publish', $user) || $asset->isAllowed('delete', $user))) {
            if (AssetHelper::isLocked($assetId, 'asset', $user->getId())) {
                $unlocked = AssetHelper::unlockForLocker($user->getId(), $assetId);
                if($unlocked) {
                    return new JsonResponse(['success' => true, 'message' => "asset has been unlocked for editing"]);
                }

                return new JsonResponse(['success' => true, 'message' => "asset is locked for editing"], 403);
            }

            return new JsonResponse(['success' => false, 'message' => "asset is already unlocked for editing"]);
        }

        throw new AccessDeniedHttpException(
            'Missing the permission to create new assets in the folder: ' . $asset->getParent()->getRealFullPath()
        );
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     * @Route("/add-asset", name="upload_asset", methods={"POST", "OPTIONS"})
     */
    public function add(Config              $pimcoreConfig,
                        TranslatorInterface $translator,
                        AssetHelper $assetHelper): Response
    {
        // Check if request is authenticated properly
        $user = $this->authManager->authenticate();
        try {
            $defaultUploadPath = $pimcoreConfig['assets']['default_upload_path'] ?? '/';

            /** @var UploadedFile $uploadedFile */
            $uploadedFile = $this->request->files->get('file');
            $sourcePath = $uploadedFile->getRealPath();
            $filename = $uploadedFile->getClientOriginalName();
            $filename = Element\Service::getValidKey($filename, 'asset');

            if (empty($filename)) {
                throw new Exception('The filename of the asset is empty');
            }

            if ($this->request->query->has('parentId')) {
                $parentAsset = Asset::getById((int)$this->request->query->get('parentId'));
                if(!$parentAsset instanceof Asset) {
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
                $filename = Element\Service::getValidKey($event->getFilename(), 'asset');
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
                return $assetHelper->updateAsset($asset, $sourcePath, $filename, $user, $translator);
            } else if (Asset\Service::pathExists($parentAsset->getRealFullPath().'/'.$filename)) {
                $asset = Asset::getByPath($parentAsset->getRealFullPath().'/'.$filename);
                return $assetHelper->updateAsset($asset, $sourcePath, $filename, $user, $translator);
            } else {
                if (!$parentAsset->isAllowed('create', $user) && !$this->authManager->isAllowed($parentAsset, 'create', $user)) {
                    throw new AccessDeniedHttpException(
                        'Missing the permission to create new assets in the folder: ' . $parentAsset->getRealFullPath()
                    );
                }
                $asset = Asset::create($parentAsset->getId(), [
                    'filename' => $filename,
                    'sourcePath' => $sourcePath,
                    'userOwner' => $user->getId(),
                    'userModification' => $user->getId(),
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

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return Response
     * @Route("/download-asset", name="download_asset", methods={"GET", "OPTIONS"})
     * @throws \Doctrine\DBAL\Exception
     */
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
            } else if ($asset instanceof Asset\Image) {
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
        return $response->setCallback(function () use ($stream) {
            fpassthru($stream);
        });
    }
}
