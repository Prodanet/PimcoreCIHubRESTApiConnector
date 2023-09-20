<?php

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Helper;

use CIHub\Bundle\SimpleRESTAdapterBundle\Exception\InvalidParameterException;
use CIHub\Bundle\SimpleRESTAdapterBundle\Exception\NotFoundException;
use CIHub\Bundle\SimpleRESTAdapterBundle\Flyststem\Concatenate;
use CIHub\Bundle\SimpleRESTAdapterBundle\Manager\AuthManager;
use CIHub\Bundle\SimpleRESTAdapterBundle\Model\ChunkUploadResponse;
use CIHub\Bundle\SimpleRESTAdapterBundle\Model\DatahubUploadSession;
use CIHub\Bundle\SimpleRESTAdapterBundle\Model\UploadPart;
use Exception;
use League\Flysystem\FilesystemException;
use Pimcore\Config;
use Pimcore\Model\Asset;
use Pimcore\Model\Element\Service;
use Pimcore\Model\User;
use Pimcore\Tool\Storage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Uid\Ulid;

final class UploadHelper
{
    protected User $user;

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function __construct(private Config          $pimcoreConfig,
                                private RouterInterface $router,
                                private AuthManager     $authManager
    )
    {
        $this->user = $this->authManager->authenticate();
    }

    public function getSessionResponse(Request $request, string $id, string $config, $partSize, int $processed = 0, int $totalParts = 0): array
    {
        $response = new ChunkUploadResponse($id);
        $response->setPartSize($partSize);
        $response->setNumPartsProcessed($processed);
        $response->setTotalParts($totalParts);
        $response->addEndpoint($this->generateUrl('datahub_rest_endpoints_asset_upload_abort', ['config' => $config, 'id' => $id]));
        $response->addEndpoint($this->generateUrl('datahub_rest_endpoints_asset_upload_commit', ['config' => $config, 'id' => $id]));
        $response->addEndpoint($this->generateUrl('datahub_rest_endpoints_asset_upload_list_parts', ['config' => $config, 'id' => $id]));
        $response->addEndpoint($this->generateUrl('datahub_rest_endpoints_asset_upload_status', ['config' => $config, 'id' => $id]));
        $response->addEndpoint($this->generateUrl('datahub_rest_endpoints_asset_upload_part', ['config' => $config, 'id' => $id]));

        return $response->toArray();
    }

    private function generateUrl(string $route, array $parameters = []): string
    {
        return $this->router->generate($route, $parameters);
    }

    /**
     * @throws Exception
     */
    public function createSession(Request $request, int $partSize): DatahubUploadSession
    {
        $fileName = $request->get('file_name');
        $fileName = Service::getValidKey($fileName, 'asset');

        $fileSize = (int)$request->get('file_size');
        $assetId = (int)$request->get('asset_id', 0);

        if (!isset($fileName, $fileSize)) {
            throw new InvalidParameterException(['file_size', 'file_name']);
        }
        $parentId = $request->request->has('parentId', null);
        $parentId = $this->getParent($parentId, $assetId);

        $totalParts = ($fileSize / $partSize);

        $id = new Ulid();
        $session = new DatahubUploadSession();
        $session->setId($id);
        $session->setFileName($fileName);
        $session->setAssetId($assetId);
        $session->setParentId($parentId);
        $session->setParts([]);
        $session->setTotalParts($totalParts);
        $session->setFileSize($fileSize);
        $session->save();

        return $session;
    }

    public function deleteSession(string $id): void
    {
        $session = DatahubUploadSession::getById($id);
        $session->delete();
    }

    /**
     * @throws FilesystemException
     * @throws Exception
     */
    public function commitSession(string $id): array
    {
        $session = DatahubUploadSession::getById($id);
        $parentId = $this->getParent($session->getParentId(), $session->getAssetId());

        $storage = Storage::get('temp');

        $concatenate = new Concatenate($storage);
        $storage->write($session->getTemporaryPath(), '');

        try {
            foreach ($session->getParts() as $part) {
                $partTemporaryFile = $session->getTemporaryPartFilename($part->getId());
                $concatenate->handle($session->getTemporaryPath(), $partTemporaryFile);
                $storage->delete($partTemporaryFile);
            }
            $stream = stream_get_meta_data($storage->readStream($session->getTemporaryPath()));
            $asset = Asset::create($parentId, [
                'filename' => $session->getFileName(),
                'sourcePath' => $stream['uri'],
                'userOwner' => $this->user->getId(),
                'userModification' => $this->user->getId(),
            ]);
            @unlink($session->getFileName());
            $session->delete();

            return [
                'id' => $asset->getId(),
                'path' => $asset->getFullPath(),
                'type' => $asset->getType(),
            ];
        } catch (Exception $exception) {
            return [
                'message' => $exception->getMessage(),
            ];
        }
    }

    public function hasSession(string $id): bool
    {
        $session = DatahubUploadSession::hasById($id);
        if (!empty($session)) {
            return true;
        }

        return false;
    }

    public function getSession(string $id): DatahubUploadSession
    {
        $session = DatahubUploadSession::getById($id);
        if (!empty($session)) {
            return $session;
        }

        throw new NotFoundException('Session not found');
    }

    public function uploadPart(DatahubUploadSession                                                     $session,
                               #[LanguageLevelTypeAware(["7.2" => "HashContext"], default: "resource")] $content,
                               int                                                                      $size,
                               int                                                                      $ordinal
    ): UploadPart
    {
        $ctx = hash_init('sha3-512');
        hash_update_stream($ctx, $content);
        $hash = hash_final($ctx);

        $id = new Ulid();

        $part = new UploadPart();
        $part->setId($id);
        $part->setHash($hash);
        $part->setSize($size);
        $part->setOrdinal($ordinal);
        rewind($content);

        $storage = Storage::get('temp');
        $storage->writeStream($session->getTemporaryPartFilename($id), $content);

        $session->addPart($part);
        $session->save();

        return $part;
    }

    /**
     * @param int|null $parentId
     * @param int $assetId
     * @return int
     * @throws Exception
     */
    private function getParent(?int $parentId, int $assetId): int
    {
        $defaultUploadPath = $this->pimcoreConfig['assets']['default_upload_path'] ?? '/';
        if ($parentId !== null) {
            $parentAsset = Asset::getById($parentId);
            if (!$parentAsset instanceof Asset) {
                throw new NotFoundException('Parent does not exist');
            }
            $parentId = $parentAsset->getId();
        } else {
            $parentId = Asset\Service::createFolderByPath($defaultUploadPath)->getId();
            $parentAsset = Asset::getById($parentId);
        }

        if (!$parentAsset->isAllowed('create', $this->user) && !$this->authManager->isAllowed($parentAsset, 'create', $this->user)) {
            throw new AccessDeniedHttpException(
                'Missing the permission to create new assets in the folder: ' . $parentAsset->getRealFullPath()
            );
        }

        if ($assetId !== 0) {
            $asset = Asset::getById($assetId);
            if (!$asset instanceof Asset) {
                throw new NotFoundException('Asset does not exist');
            }

            if (!$asset->isAllowed('update', $this->user) && !$this->authManager->isAllowed($asset, 'update', $this->user)) {
                throw new AccessDeniedHttpException(
                    'Missing the permission to update asset: ' . $asset->getId()
                );
            }
        }
        return $parentId;
    }
}
