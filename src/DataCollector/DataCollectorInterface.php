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

namespace CIHub\Bundle\SimpleRESTAdapterBundle\DataCollector;

use CIHub\Bundle\SimpleRESTAdapterBundle\Reader\ConfigReader;

interface DataCollectorInterface
{
    /**
     * Collects the data appropriate for the provided value and config.
     *
     * @return array<int|string, mixed>
     */
    public function collect(mixed $value, ConfigReader $reader): array;

    /**
     * Checks if the current data collector supports the provided value.
     */
    public function supports(mixed $value): bool;
}
