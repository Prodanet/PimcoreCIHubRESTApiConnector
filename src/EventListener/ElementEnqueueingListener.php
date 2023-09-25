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

namespace CIHub\Bundle\SimpleRESTAdapterBundle\EventListener;

use CIHub\Bundle\SimpleRESTAdapterBundle\Guard\WorkspaceGuardInterface;
use CIHub\Bundle\SimpleRESTAdapterBundle\Loader\CompositeConfigurationLoader;
use CIHub\Bundle\SimpleRESTAdapterBundle\Manager\IndexManager;
use CIHub\Bundle\SimpleRESTAdapterBundle\Messenger\DeleteIndexElementMessage;
use CIHub\Bundle\SimpleRESTAdapterBundle\Messenger\UpdateIndexElementMessage;
use CIHub\Bundle\SimpleRESTAdapterBundle\Reader\ConfigReader;
use Pimcore\Event\AssetEvents;
use Pimcore\Event\DataObjectEvents;
use Pimcore\Event\Model\AssetEvent;
use Pimcore\Event\Model\DataObjectEvent;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject;
use Pimcore\Model\Element\ElementInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class ElementEnqueueingListener implements EventSubscriberInterface
{
    private CompositeConfigurationLoader $configLoader;

    private IndexManager $indexManager;

    private MessageBusInterface $messageBus;

    private WorkspaceGuardInterface $workspaceGuard;

    public function __construct(
        CompositeConfigurationLoader $configLoader,
        IndexManager $indexManager,
        MessageBusInterface $messageBus,
        WorkspaceGuardInterface $workspaceGuard
    ) {
        $this->configLoader = $configLoader;
        $this->indexManager = $indexManager;
        $this->messageBus = $messageBus;
        $this->workspaceGuard = $workspaceGuard;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AssetEvents::POST_ADD => 'enqueueAsset',
            AssetEvents::POST_UPDATE => 'enqueueAsset',
            AssetEvents::PRE_DELETE => 'removeAsset',
            DataObjectEvents::POST_ADD => 'enqueueObject',
            DataObjectEvents::POST_UPDATE => 'enqueueObject',
            DataObjectEvents::PRE_DELETE => 'removeObject',
        ];
    }

    public function enqueueAsset(AssetEvent $event): void
    {
        $type = 'asset';
        $asset = $event->getAsset();

        $configurations = $this->configLoader->loadConfigs();

        foreach ($configurations as $configuration) {
            $name = $configuration->getName();
            $reader = new ConfigReader($configuration->getConfiguration());

            // Check if assets are enabled
            if (!$reader->isAssetIndexingEnabled()) {
                continue;
            }

            if ($this->workspaceGuard->isGranted($asset, $type, $reader)) {
                $this->messageBus->dispatch(new UpdateIndexElementMessage($asset->getId(), $type, $name));

                // Index all folders above the asset
                $this->enqueueParentFolders($asset->getParent(), Asset\Folder::class, $type, $name);
            } else {
                $this->messageBus->dispatch(
                    new DeleteIndexElementMessage(
                        $asset->getId(),
                        $type,
                        $this->indexManager->getIndexName($asset, $name)
                    )
                );
            }
        }
    }

    public function enqueueObject(DataObjectEvent $event): void
    {
        $type = 'object';
        $object = $event->getObject();

        if (!$object instanceof DataObject\Concrete) {
            return;
        }

        $configurations = $this->configLoader->loadConfigs();

        foreach ($configurations as $configuration) {
            $name = $configuration->getName();
            $reader = new ConfigReader($configuration->getConfiguration());
            $objectClassNames = $reader->getObjectClassNames();

            // Check if object class is configured
            if (!\in_array($object->getClassName(), $objectClassNames, true)) {
                continue;
            }

            if ($this->workspaceGuard->isGranted($object, $type, $reader)) {
                $this->messageBus->dispatch(new UpdateIndexElementMessage($object->getId(), $type, $name));

                // Index all folders above the object
                $this->enqueueParentFolders($object->getParent(), DataObject\Folder::class, $type, $name);
            } else {
                $this->messageBus->dispatch(
                    new DeleteIndexElementMessage(
                        $object->getId(),
                        $type,
                        $this->indexManager->getIndexName($object, $name)
                    )
                );
            }
        }
    }

    public function removeAsset(AssetEvent $event): void
    {
        $type = 'asset';
        $asset = $event->getAsset();

        $configurations = $this->configLoader->loadConfigs();

        foreach ($configurations as $configuration) {
            $name = $configuration->getName();
            $reader = new ConfigReader($configuration->getConfiguration());

            // Check if assets are enabled
            if (!$reader->isAssetIndexingEnabled()) {
                continue;
            }

            if ($this->workspaceGuard->isGranted($asset, $type, $reader)) {
                $this->messageBus->dispatch(
                    new DeleteIndexElementMessage(
                        $asset->getId(),
                        $type,
                        $this->indexManager->getIndexName($asset, $name)
                    )
                );
            }
        }
    }

    public function removeObject(DataObjectEvent $event): void
    {
        $type = 'object';
        $object = $event->getObject();

        if (!$object instanceof DataObject\Concrete) {
            return;
        }

        $configurations = $this->configLoader->loadConfigs();

        foreach ($configurations as $configuration) {
            $name = $configuration->getName();
            $reader = new ConfigReader($configuration->getConfiguration());
            $objectClassNames = $reader->getObjectClassNames();

            // Check if object class is configured
            if (!\in_array($object->getClassName(), $objectClassNames, true)) {
                continue;
            }

            if ($this->workspaceGuard->isGranted($object, $type, $reader)) {
                $this->messageBus->dispatch(
                    new DeleteIndexElementMessage(
                        $object->getId(),
                        $type,
                        $this->indexManager->getIndexName($object, $name)
                    )
                );
            }
        }
    }

    private function enqueueParentFolders(
        ?ElementInterface $parent,
        string $folderClass,
        string $type,
        string $name
    ): void {
        while ($parent instanceof $folderClass && 1 !== $parent->getId()) {
            $this->messageBus->dispatch(new UpdateIndexElementMessage($parent->getId(), $type, $name));
            $parent = $parent->getParent();
        }
    }
}
