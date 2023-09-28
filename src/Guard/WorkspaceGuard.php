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

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Guard;

use CIHub\Bundle\SimpleRESTAdapterBundle\Reader\ConfigReader;
use CIHub\Bundle\SimpleRESTAdapterBundle\Utils\WorkspaceSorter;
use Pimcore\Model\Element\ElementInterface;

final class WorkspaceGuard implements WorkspaceGuardInterface
{
    public function isGranted(ElementInterface $element, string $elementType, ConfigReader $reader): bool
    {
        $workspace = WorkspaceSorter::sort($reader->getWorkspace($elementType), WorkspaceSorter::HIGHEST_SPECIFICITY);

        // No workspace configuration found for element type
        if ([] === $workspace) {
            return false;
        }

        foreach ($workspace as $config) {
            // Check if element is within folder
            if (!str_contains($element->getFullPath(), (string) $config['cpath'])) {
                continue;
            }

            return $config['read'];
        }

        return false;
    }
}
