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

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Utils;

final class WorkspaceSorter
{
    public const LOWEST_SPECIFICITY = 0;

    public const HIGHEST_SPECIFICITY = 1;

    /**
     * Sorts the workspace either by lowest or highest specificity.
     *
     * @param array<int, array> $workspace
     *
     * @return array<int, array>
     */
    public static function sort(array $workspace, int $sortFlag = self::LOWEST_SPECIFICITY): array
    {
        usort($workspace, static fn (array $left, array $right): int => mb_substr_count((string) $left['cpath'], '/') - mb_substr_count((string) $right['cpath'], '/'));

        return self::LOWEST_SPECIFICITY === $sortFlag ? $workspace : array_reverse($workspace);
    }
}
