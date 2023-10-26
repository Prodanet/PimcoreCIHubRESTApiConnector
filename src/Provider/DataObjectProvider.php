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

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Provider;

use CIHub\Bundle\SimpleRESTAdapterBundle\DataCollector\CompositeDataCollector;
use CIHub\Bundle\SimpleRESTAdapterBundle\Reader\ConfigReader;
use CIHub\Bundle\SimpleRESTAdapterBundle\Traits\LockedTrait;
use Pimcore\Localization\LocaleService;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Service;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Tool;
use Webmozart\Assert\Assert;

final class DataObjectProvider implements ProviderInterface
{
    use LockedTrait;
    public function __construct(private CompositeDataCollector $compositeDataCollector)
    {
    }

    public function getIndexData(ElementInterface $element, ConfigReader $configReader): array
    {
        /* @var DataObject\AbstractObject $element */
        Assert::isInstanceOf($element, AbstractObject::class);

        $data = [
            'system' => $this->getSystemValues($element),
        ];

        if ($element instanceof Concrete) {
            $data['data'] = $this->getDataValues($element, $configReader);
        }

        return $data;
    }

    /**
     * Returns the data values of an object.
     *
     * @return array<string, mixed>
     */
    private function getDataValues(Concrete $concrete, ConfigReader $configReader): array
    {
        $objectSchema = $configReader->extractObjectSchema($concrete->getClassName());
        $fields = $objectSchema['columnConfig'] ?? [];

        $data = Service::getCsvDataForObject(
            $concrete,
            Tool::getDefaultLanguage(),
            array_keys($fields),
            $fields,
            new LocaleService(),
            true
        );

        // Collect data for special field types, such as images/hotspot images/image galleries
        foreach ($fields as $key => $field) {
            $fieldValue = $this->compositeDataCollector->collect($concrete, $key, $configReader);

            if (null === $fieldValue) {
                continue;
            }

            $data[$key] = $fieldValue;
        }

        return $data;
    }

    /**
     * Returns the system values of an object.
     *
     * @return array<string, mixed>
     */
    private function getSystemValues(AbstractObject $object): array
    {
        return [
            'id' => $object->getId(),
            'key' => $object->getKey(),
            'fullPath' => $object->getFullPath(),
            'parentId' => $object->getParentId(),
            'type' => 'object',
            'subtype' => $object->getType(),
            'hasChildren' => $object->hasChildren(),
            'creationDate' => $object->getCreationDate(),
            'modificationDate' => $object->getModificationDate(),
            'locked' => $this->isLocked($object->getId(), 'object'),
        ];
    }
}
