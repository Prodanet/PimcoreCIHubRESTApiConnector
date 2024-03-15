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

use CIHub\Bundle\SimpleRESTAdapterBundle\Manager\AuthManager;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\ClassDefinition\Data\ManyToManyRelation;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\Element\Editlock;
use Pimcore\Model\Element\Service;
use Pimcore\Model\Element\ValidationException;
use Pimcore\Model\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AssetHelper
{
    public function __construct(protected AuthManager $authManager)
    {
    }

    public function isLocked(int $cid, string $ctype, int $userId): bool
    {
        if (($lock = Editlock::getByElement($cid, $ctype)) instanceof Editlock) {
            if ((time() - $lock->getDate()) > 3600 && $lock->getUser()->getId() == $userId) {
                // lock is out of date unlock it
                Editlock::unlock($cid, $ctype);

                return false;
            }

            return true;
        }

        return false;
    }

    public function unlockForLocker(int $userId, int $assetId): bool
    {
        $editlock = Editlock::getByElement($assetId, 'asset');

        if ($editlock->getUserId() === $userId) {
            Editlock::unlock($assetId, 'asset');

            return true;
        }

        return false;
    }

    /**
     * @throws ValidationException
     */
    public function validateManyToManyRelationAssetType(array $context, string $filename, string $sourcePath): void
    {
        if (isset($context['containerType'], $context['objectId'], $context['fieldname'])
            && 'object' === $context['containerType']
            && ($object = Concrete::getById($context['objectId'])) instanceof Concrete
        ) {
            $fieldDefinition = $object->getClass()->getFieldDefinition($context['fieldname']);
            if (!$fieldDefinition instanceof ManyToManyRelation) {
                return;
            }

            $mimeType = MimeTypes::getDefault()->guessMimeType($sourcePath);
            $type = Asset::getTypeFromMimeMapping($mimeType, $filename);

            $allowedAssetTypes = $fieldDefinition->getAssetTypes();
            $allowedAssetTypes = array_column($allowedAssetTypes, 'assetTypes');

            if (
                !(
                    $fieldDefinition->getAssetsAllowed()
                    && ([] === $allowedAssetTypes || \in_array($type, $allowedAssetTypes, true))
                )
            ) {
                throw new ValidationException(sprintf('Invalid relation in field `%s` [type: %s]', $context['fieldname'], $type));
            }
        }
    }

    /**
     * @throws \Exception
     */
    public function updateAsset(Asset $asset, string $sourcePath, string $filename, User $user, TranslatorInterface $translator): JsonResponse
    {
        $parentAsset = $asset->getParent();
        if (!$parentAsset->isAllowed('update', $user)) {
            throw new AccessDeniedHttpException('Missing the permission to create new assets in the folder: '.$parentAsset->getRealFullPath());
        }

        $mimetype = MimeTypes::getDefault()->guessMimeType($sourcePath);
        $newType = Asset::getTypeFromMimeMapping($mimetype, $filename);

        if ($newType !== $asset->getType()) {
            @unlink($sourcePath);

            return new JsonResponse([
                'success' => false,
                'message' => sprintf($translator->trans('asset_type_change_not_allowed', [], 'admin'), $asset->getType(), $newType),
            ]);
        }

        $stream = fopen($sourcePath, 'r+');
        $asset->setStream($stream);
        $asset->setCustomSetting('thumbnails', null);
        $asset->setUserModification($user->getId());

        $newFileExt = pathinfo($filename, \PATHINFO_EXTENSION);
        $currentFileExt = pathinfo((string) $asset->getFilename(), \PATHINFO_EXTENSION);
        if ($newFileExt !== $currentFileExt) {
            $newFilename = preg_replace('/\.'.$currentFileExt.'$/i', '.'.$newFileExt, (string) $asset->getFilename());
            $newFilename = Service::getSafeCopyName($newFilename, $asset->getParent());
            $asset->setFilename($newFilename);
        }

        if ($asset->isAllowed('publish', $user)) {
            $asset->save();

            $jsonResponse = new JsonResponse([
                'id' => $asset->getId(),
                'path' => $asset->getRealFullPath(),
                'success' => true,
            ]);

            // set content-type to text/html, otherwise (when application/json is sent) chrome will complain in
            // Ext.form.Action.Submit and mark the submission as failed
            $jsonResponse->headers->set('Content-Type', 'text/html');
            @unlink($sourcePath);

            return $jsonResponse;
        }

        @unlink($sourcePath);

        throw new \Exception('missing permission');
    }

    public function lock(int $cid, string $ctype, int $userId): Editlock|bool
    {
        $editlock = new Editlock();
        $editlock->setCid($cid);
        $editlock->setCtype($ctype);
        $editlock->setDate(time() + (60 * 10));
        $editlock->setUserId($userId);
        $editlock->save();

        return $editlock;
    }
}
