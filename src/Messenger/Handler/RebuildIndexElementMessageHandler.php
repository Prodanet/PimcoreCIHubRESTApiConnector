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
    const CHUNK_SIZE = 100;

    public function __construct(
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @throws \Exception
     */
    public function __invoke(RebuildIndexElementMessage $rebuildIndexElementMessage): void
    {
        $maxId = $this->getDb()->executeQuery('SELECT MAX(id) FROM assets')->fetchNumeric()[0];
        $start = 0;
        for ($end = self::CHUNK_SIZE; $end <= $maxId; $end += self::CHUNK_SIZE) {
            $assets = $this->getDb()
                ->executeQuery("SELECT id, parentId FROM assets WHERE id >= $start AND id < $end")
                ->fetchAllAssociative()
            ;
            if (!empty($assets)) {
                foreach ($assets as $asset) {
                    $this->messageBus->dispatch(new UpdateIndexElementMessage($asset['id'], 'asset', $rebuildIndexElementMessage->name));
                    $this->enqueueParentFolders(Asset::getById($asset['parentId']), Folder::class, 'asset', $rebuildIndexElementMessage->name);
                }
            }

            $start = $end;
        }

        $maxId = $this->getDb()->executeQuery('SELECT MAX(id) FROM objects')->fetchNumeric()[0];
        $start = 0;
        for ($end = self::CHUNK_SIZE; $end <= $maxId; $end += self::CHUNK_SIZE) {
            $objects = $this->getDb()
                ->executeQuery("SELECT id, parentId FROM objects WHERE id >= $start AND id < $end")
                ->fetchAllAssociative()
            ;
            if (!empty($objects)) {
                foreach ($objects as $object) {
                    $this->messageBus->dispatch(new UpdateIndexElementMessage($object['id'], 'object', $rebuildIndexElementMessage->name));
                    $this->enqueueParentFolders(Asset::getById($object['parentId']), DataObject\Folder::class, 'object', $rebuildIndexElementMessage->name);
                }
            }

            $start = $end;
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
