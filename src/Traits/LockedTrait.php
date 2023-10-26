<?php

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