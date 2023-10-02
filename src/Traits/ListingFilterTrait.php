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

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Traits;

use CIHub\Bundle\SimpleRESTAdapterBundle\Exception\InvalidParameterException;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\User;

trait ListingFilterTrait
{
    public function filterAccessibleByUser(User $user, ElementInterface $element): ?string
    {
        if (!$user->isAdmin()) {
            $userIds = $user->getRoles();
            $currentUserId = $user->getId();
            $userIds[] = $currentUserId;

            $inheritedPermission = $element->getDao()->isInheritingPermission('list', $userIds);

            if ($element instanceof Asset) {
                $anyAllowedRowOrChildren = 'EXISTS(SELECT list FROM users_workspaces_asset uwa WHERE userId IN ('.implode(',', $userIds).') AND list=1 AND LOCATE(CONCAT(`path`,filename),cpath)=1 AND
                NOT EXISTS(SELECT list FROM users_workspaces_asset WHERE userId ='.$currentUserId.'  AND list=0 AND cpath = uwa.cpath))';
                $isDisallowedCurrentRow = 'EXISTS(SELECT list FROM users_workspaces_asset WHERE userId IN ('.implode(',', $userIds).')  AND cid = id AND list=0)';
            } elseif ($element instanceof DataObject) {
                $anyAllowedRowOrChildren = 'EXISTS(SELECT list FROM users_workspaces_object uwa WHERE userId IN ('.implode(',', $userIds).') AND list=1 AND LOCATE(CONCAT(`path`,filename),cpath)=1 AND
                NOT EXISTS(SELECT list FROM users_workspaces_object WHERE userId ='.$currentUserId.'  AND list=0 AND cpath = uwa.cpath))';
                $isDisallowedCurrentRow = 'EXISTS(SELECT list FROM users_workspaces_object WHERE userId IN ('.implode(',', $userIds).')  AND cid = id AND list=0)';
            } else {
                throw new InvalidParameterException('Type ['.$element.'] is not supported');
            }

            return 'IF('.$anyAllowedRowOrChildren.',1,IF('.$inheritedPermission.', '.$isDisallowedCurrentRow.' = 0, 0)) = 1';
        }

        return null;
    }
}
