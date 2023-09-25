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

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Model\Event;

use Symfony\Contracts\EventDispatcher\Event;

class ConfigurationEvent extends Event
{
    /**
     * @var array<string, array>
     */
    private array $configuration;

    /**
     * @var array<string, array>
     */
    private array $priorConfiguration;

    /**
     * @param array<string, array> $configuration
     * @param array<string, array> $priorConfiguration
     */
    public function __construct(array $configuration, array $priorConfiguration = [])
    {
        $this->configuration = $configuration;
        $this->priorConfiguration = $priorConfiguration;
    }

    /**
     * @return array<string, array>
     */
    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    /**
     * @return array<string, array>
     */
    public function getPriorConfiguration(): array
    {
        return $this->priorConfiguration;
    }
}
