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

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Helper;

use CIHub\Bundle\SimpleRESTAdapterBundle\Exception\InvalidParameterException;
use CIHub\Bundle\SimpleRESTAdapterBundle\Exception\NotFoundException;
use CIHub\Bundle\SimpleRESTAdapterBundle\Flyststem\Concatenate;
use CIHub\Bundle\SimpleRESTAdapterBundle\Manager\AuthManager;
use CIHub\Bundle\SimpleRESTAdapterBundle\Model\ChunkUploadResponse;
use CIHub\Bundle\SimpleRESTAdapterBundle\Model\DatahubUploadSession;
use CIHub\Bundle\SimpleRESTAdapterBundle\Model\UploadPart;
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
    private User $user;

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function __construct(
        private Config $pimcoreConfig,
        private RouterInterface $router,
        private AuthManager $authManager
    ) {
        $this->user = $this->authManager->authenticate();
    }

    public function getSessionResponse(Request $request, string $id, string $config, int $partSize, int $processed = 0, int $totalParts = 0): array
    {
        $chunkUploadResponse = new ChunkUploadResponse($id);
        $chunkUploadResponse->setPartSize($partSize);
        $chunkUploadResponse->setNumPartsProcessed($processed);
        $chunkUploadResponse->setTotalParts($totalParts);
        $chunkUploadResponse->addEndpoint($this->generateUrl('datahub_rest_endpoints_upload_upload_abort', ['config' => $config, 'id' => $id]));
        $chunkUploadResponse->addEndpoint($this->generateUrl('datahub_rest_endpoints_upload_upload_commit', ['config' => $config, 'id' => $id]));
        $chunkUploadResponse->addEndpoint($this->generateUrl('datahub_rest_endpoints_upload_upload_list_parts', ['config' => $config, 'id' => $id]));
        $chunkUploadResponse->addEndpoint($this->generateUrl('datahub_rest_endpoints_upload_upload_status', ['config' => $config, 'id' => $id]));
        $chunkUploadResponse->addEndpoint($this->generateUrl('datahub_rest_endpoints_upload_upload_part', ['config' => $config, 'id' => $id]));

        return $chunkUploadResponse->toArray();
    }

    private function generateUrl(string $route, array $parameters = []): string
    {
        return $this->router->generate($route, $parameters);
    }

    /**
     * @throws \Exception
     */
    public function createSession(Request $request, int $partSize): DatahubUploadSession
    {
        $fileName = $request->get('file_name');
        $fileName = Service::getValidKey($fileName, 'asset');

        $fileSize = (int) $request->get('filesize');
        $assetId = $request->request->get('asset_id', null);

        if (!isset($fileName, $fileSize)) {
            throw new InvalidParameterException(['filesize', 'file_name']);
        }

        $parentId = $request->request->get('parentId');
        $parentId = $this->getParent($parentId, $assetId);

        if (null !== $assetId) {
            $asset = Asset::getById($assetId);
            if ($asset instanceof Asset) {
                if (!$asset->isAllowed('allowOverwrite', $this->user)) {
                    throw new AccessDeniedHttpException('Missing the permission to overwrite asset: '.$asset->getId());
                }
            } else {
                throw new NotFoundException('Asset with id ['.$assetId."] doesn't exist");
            }
        }

        $totalParts = ($fileSize / $partSize);

        $ulid = new Ulid();
        $datahubUploadSession = new DatahubUploadSession();
        $datahubUploadSession->setId($ulid);
        $datahubUploadSession->setFileName($fileName);
        $datahubUploadSession->setAssetId($assetId);
        $datahubUploadSession->setParentId($parentId);
        $datahubUploadSession->setParts([]);
        $datahubUploadSession->setTotalParts($totalParts);
        $datahubUploadSession->setFileSize($fileSize);
        $datahubUploadSession->save();

        return $datahubUploadSession;
    }

    public function deleteSession(string $id): void
    {
        $datahubUploadSession = DatahubUploadSession::getById($id);
        $datahubUploadSession->delete();
    }

    /**
     * @throws FilesystemException
     * @throws \Exception
     */
    public function commitSession(string $id): array
    {
        $datahubUploadSession = DatahubUploadSession::getById($id);
        $parentId = $this->getParent($datahubUploadSession->getParentId(), $datahubUploadSession->getAssetId());

        $filesystemOperator = Storage::get('temp');

        $concatenate = new Concatenate($filesystemOperator);
        $filesystemOperator->write($datahubUploadSession->getTemporaryPath(), '');

        try {
            foreach ($datahubUploadSession->getParts() as $part) {
                $partTemporaryFile = $datahubUploadSession->getTemporaryPartFilename($part->getId());
                $concatenate->handle($datahubUploadSession->getTemporaryPath(), $partTemporaryFile);
                $filesystemOperator->delete($partTemporaryFile);
            }

            $stream = stream_get_meta_data($filesystemOperator->readStream($datahubUploadSession->getTemporaryPath()));
            if (null === $datahubUploadSession->getAssetId()) {
                $asset = Asset::create($parentId, [
                    'filename' => $datahubUploadSession->getFileName(),
                    'sourcePath' => $stream['uri'],
                    'userOwner' => $this->user->getId(),
                    'userModification' => $this->user->getId(),
                ]);
            } else {
                $asset = Asset::getById($datahubUploadSession->getAssetId());
                if ($asset instanceof Asset) {
                    if ($asset->isAllowed('allowOverwrite', $this->user)) {
                        $asset->setFilename($datahubUploadSession->getFileName());
                        $asset->setStream($filesystemOperator->readStream($datahubUploadSession->getTemporaryPath()));
                        $asset->setUserOwner($this->user->getId());
                        $asset->setUserModification($this->user->getId());
                        $asset->save();
                    } else {
                        throw new AccessDeniedHttpException('Missing the permission to overwrite asset: '.$asset->getId());
                    }
                } else {
                    throw new \Exception('Asset with id ['.$id."] doesn't exist");
                }
            }

            @unlink($datahubUploadSession->getFileName());
            $datahubUploadSession->delete();

            return [
                'id' => $asset->getId(),
                'path' => $asset->getFullPath(),
                'type' => $asset->getType(),
            ];
        } catch (\Exception $exception) {
            return [
                'message' => $exception->getMessage(),
            ];
        }
    }

    public function hasSession(string $id): bool
    {
        $datahubUploadSession = DatahubUploadSession::hasById($id);

        return $datahubUploadSession instanceof DatahubUploadSession;
    }

    public function getSession(string $id): DatahubUploadSession
    {
        $datahubUploadSession = DatahubUploadSession::getById($id);
        if ($datahubUploadSession instanceof DatahubUploadSession) {
            return $datahubUploadSession;
        }

        throw new NotFoundException('Session not found');
    }

    /**
     * @throws FilesystemException
     */
    public function uploadPart(
        DatahubUploadSession $datahubUploadSession,
        #[LanguageLevelTypeAware(['7.2' => 'HashContext'], default: 'resource')]
                             $content,
        int $size,
        int $ordinal
    ): UploadPart {
        $hashContext = hash_init('sha3-512');
        hash_update_stream($hashContext, $content);
        $hash = hash_final($hashContext);

        $ulid = new Ulid();

        $uploadPart = new UploadPart();
        $uploadPart->setId($ulid);
        $uploadPart->setHash($hash);
        $uploadPart->setSize($size);
        $uploadPart->setOrdinal($ordinal);
        rewind($content);

        $storage = Storage::get('temp');
        $storage->writeStream($datahubUploadSession->getTemporaryPartFilename($ulid), $content);

        $datahubUploadSession->addPart($uploadPart);
        $datahubUploadSession->save();

        return $uploadPart;
    }

    /**
     * @throws \Exception
     */
    private function getParent(?int $parentId, ?int $assetId): int
    {
        $defaultUploadPath = $this->pimcoreConfig['assets']['default_upload_path'] ?? '/';
        if (null !== $parentId) {
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
            throw new AccessDeniedHttpException('Missing the permission to create new assets in the folder: '.$parentAsset->getRealFullPath());
        }

        if (null !== $assetId) {
            $asset = Asset::getById($assetId);
            if (!$asset instanceof Asset) {
                throw new NotFoundException('Asset does not exist');
            }

            if (!$asset->isAllowed('update', $this->user) && !$this->authManager->isAllowed($asset, 'update', $this->user)) {
                throw new AccessDeniedHttpException('Missing the permission to update asset: '.$asset->getId());
            }
        }

        return $parentId;
    }
}
