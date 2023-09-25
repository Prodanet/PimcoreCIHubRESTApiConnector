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

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Repository;

use Pimcore\Bundle\DataHubBundle\Configuration;
use Pimcore\Bundle\DataHubBundle\Configuration\Dao;

final class DataHubConfigurationRepository
{
    public function findOneByName(string $name): ?Configuration
    {
        return Dao::getByName($name);
    }

    public function all(): array
    {
        return Dao::getList();
    }

    /**
     * @param array<int, string> $allowedConfigTypes
     *
     * @return Configuration[]
     */
    public function getList(array $allowedConfigTypes = []): array
    {
        $list = Dao::getList();

        if (!empty($allowedConfigTypes)) {
            $list = array_filter($list, static function ($config) use ($allowedConfigTypes) {
                return \in_array($config->getType(), $allowedConfigTypes, true);
            });
        }

        return $list;
    }

    public function getModificationDate(): bool|int
    {
        return Dao::getConfigModificationDate();
    }
}
