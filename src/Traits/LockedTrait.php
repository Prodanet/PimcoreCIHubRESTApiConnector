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

use Pimcore\Model\Element\Editlock;

trait LockedTrait
{
    public function isLocked(int $cid, string $ctype): bool
    {
        if (($lock = Editlock::getByElement($cid, $ctype)) instanceof Editlock) {
            if ((time() - $lock->getDate()) > 3600) {
                // lock is out of date unlock it
                Editlock::unlock($cid, $ctype);

                return false;
            }

            return true;
        }

        return false;
    }
}
