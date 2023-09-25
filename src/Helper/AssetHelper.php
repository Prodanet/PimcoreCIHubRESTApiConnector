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
use Pimcore\Model\Element;
use Pimcore\Model\Element\ValidationException;
use Pimcore\Model\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Contracts\Translation\TranslatorInterface;

class AssetHelper
{
    public function __construct(protected AuthManager $authManager)
    {
    }

    public function isLocked(int $cid, string $ctype, int $userId): bool
    {
        if ($lock = Element\Editlock::getByElement($cid, $ctype)) {
            if ((time() - $lock->getDate()) > 3600) {
                // lock is out of date unlock it
                Element\Editlock::unlock($cid, $ctype);

                return false;
            }

            return true;
        }

        return false;
    }

    public function unlockForLocker(int $userId, int $assetId)
    {
        $lock = Element\Editlock::getByElement($assetId, 'asset');

        if ($lock->getUserId() === $userId) {
            Element\Editlock::unlock($assetId, 'asset');

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
            && $object = Concrete::getById($context['objectId'])
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
        if (!$parentAsset->isAllowed('update', $user) && !$this->authManager->isAllowed($parentAsset, 'update', $user)) {
            throw new AccessDeniedHttpException('Missing the permission to create new assets in the folder: ' . $parentAsset->getRealFullPath());
        }

        $mimetype = MimeTypes::getDefault()->guessMimeType($sourcePath);
        $newType = Asset::getTypeFromMimeMapping($mimetype, $filename);

        if ($newType != $asset->getType()) {
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
        $currentFileExt = pathinfo($asset->getFilename(), \PATHINFO_EXTENSION);
        if ($newFileExt != $currentFileExt) {
            $newFilename = preg_replace('/\.' . $currentFileExt . '$/i', '.' . $newFileExt, $asset->getFilename());
            $newFilename = Element\Service::getSafeCopyName($newFilename, $asset->getParent());
            $asset->setFilename($newFilename);
        }

        if ($asset->isAllowed('publish', $user)) {
            $asset->save();

            $response = new JsonResponse([
                'id' => $asset->getId(),
                'path' => $asset->getRealFullPath(),
                'success' => true,
            ]);

            // set content-type to text/html, otherwise (when application/json is sent) chrome will complain in
            // Ext.form.Action.Submit and mark the submission as failed
            $response->headers->set('Content-Type', 'text/html');

            return $response;
        }

        throw new \Exception('missing permission');
    }

    public function lock(int $cid, string $ctype, int $userId): Element\Editlock|bool
    {
        $lock = new Element\Editlock();
        $lock->setCid($cid);
        $lock->setCtype($ctype);
        $lock->setDate(time());
        $lock->setUserId($userId);
        $lock->save();

        return $lock;
    }
}
