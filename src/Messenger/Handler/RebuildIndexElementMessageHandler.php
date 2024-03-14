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

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Messenger\Handler;

use CIHub\Bundle\SimpleRESTAdapterBundle\Messenger\RebuildIndexElementMessage;
use CIHub\Bundle\SimpleRESTAdapterBundle\Messenger\UpdateIndexElementMessage;
use Doctrine\DBAL\Connection;
use Pimcore\Db;
use Pimcore\Model\Asset;
use Pimcore\Model\Asset\Folder;
use Pimcore\Model\DataObject;
use Pimcore\Model\Element\ElementInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class RebuildIndexElementMessageHandler implements MessageHandlerInterface
{
    const CHUNK_SIZE  = 100;
    const TYPE_ASSET  = 'asset';
    const TYPE_OBJECT = 'object';

    public function __construct(
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @throws \Exception
     */
    public function __invoke(RebuildIndexElementMessage $rebuildIndexElementMessage): void
    {
        $this->rebuildType($rebuildIndexElementMessage, self::TYPE_ASSET);
        $this->rebuildType($rebuildIndexElementMessage, self::TYPE_OBJECT);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    private function rebuildType(RebuildIndexElementMessage $rebuildIndexElementMessage, string $type): void
    {
        $maxId = $this->getDb()->executeQuery("SELECT MAX(id) FROM {$type}s")->fetchNumeric()[0];
        for ($start = 0; $start <= $maxId; $start += self::CHUNK_SIZE) {
            $end = $start + self::CHUNK_SIZE;
            $items = $this->getDb()
                ->executeQuery("SELECT id, parentId FROM {$type}s WHERE id >= $start AND id < $end")
                ->fetchAllAssociative()
            ;
            if (!empty($items)) {
                foreach ($items as $item) {
                    $this->messageBus->dispatch(new UpdateIndexElementMessage($item['id'], $type, $rebuildIndexElementMessage->name));
                    $this->enqueueParentFolders(
                        $type == self::TYPE_ASSET ? Asset::getById($item['parentId']) : DataObject::getById($item['parentId']),
                        $type == self::TYPE_ASSET ? Folder::class : DataObject\Folder::class,
                        $type,
                        $rebuildIndexElementMessage->name,
                    );
                }
            }
        }
    }

    private function enqueueParentFolders(
        ?ElementInterface $element,
        string $folderClass,
        string $type,
        string $name,
    ): void {
        while ($element instanceof $folderClass && 1 !== $element->getId()) {
            $this->messageBus->dispatch(new UpdateIndexElementMessage($element->getId(), $type, $name));
            $element = $element->getParent();
        }
    }

    protected function getDb(): Connection
    {
        return Db::get();
    }
}
