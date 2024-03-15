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
use Pimcore\Model\DataObject\Concrete;
use Webmozart\Assert\Assert;

final readonly class CompositeDataCollector
{
    /**
     * @param iterable<DataCollectorInterface> $collectors
     */
    public function __construct(private iterable $collectors)
    {
    }

    /**
     * Loops through all data collectors to find one, that supports the provided value.
     * If the value if supported, the data collector does its thing and returns the serialized data.
     *
     * @return array<int|string, mixed>|null
     */
    public function collect(Concrete $concrete, string $fieldName, ConfigReader $configReader): ?array
    {
        $value = $concrete->getValueForFieldName($fieldName);

        foreach ($this->collectors as $collector) {
            Assert::isInstanceOf($collector, DataCollectorInterface::class);

            if (!$collector->supports($value)) {
                continue;
            }

            return $collector->collect($value, $configReader);
        }

        return null;
    }
}
