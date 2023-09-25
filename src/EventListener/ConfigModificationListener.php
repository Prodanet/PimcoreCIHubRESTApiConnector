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

use CIHub\Bundle\SimpleRESTAdapterBundle\Elasticsearch\Mapping\AssetMapping;
use CIHub\Bundle\SimpleRESTAdapterBundle\Elasticsearch\Mapping\DataObjectMapping;
use CIHub\Bundle\SimpleRESTAdapterBundle\Elasticsearch\Mapping\FolderMapping;
use CIHub\Bundle\SimpleRESTAdapterBundle\Exception\ESClientException;
use CIHub\Bundle\SimpleRESTAdapterBundle\Manager\IndexManager;
use CIHub\Bundle\SimpleRESTAdapterBundle\Messenger\InitializeEndpointMessage;
use CIHub\Bundle\SimpleRESTAdapterBundle\Model\Event\ConfigurationEvent;
use CIHub\Bundle\SimpleRESTAdapterBundle\Reader\ConfigReader;
use CIHub\Bundle\SimpleRESTAdapterBundle\SimpleRESTAdapterEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class ConfigModificationListener implements EventSubscriberInterface
{
    private IndexManager $indexManager;

    private MessageBusInterface $messageBus;

    private AssetMapping $assetMapping;

    private DataObjectMapping $objectMapping;

    private FolderMapping $folderMapping;

    public function __construct(
        IndexManager $indexManager,
        MessageBusInterface $messageBus,
        AssetMapping $assetMapping,
        DataObjectMapping $objectMapping,
        FolderMapping $folderMapping
    ) {
        $this->indexManager = $indexManager;
        $this->messageBus = $messageBus;
        $this->assetMapping = $assetMapping;
        $this->objectMapping = $objectMapping;
        $this->folderMapping = $folderMapping;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SimpleRESTAdapterEvents::CONFIGURATION_PRE_DELETE => 'onPreDelete',
            SimpleRESTAdapterEvents::CONFIGURATION_POST_SAVE => 'onPostSave',
        ];
    }

    public function onPreDelete(ConfigurationEvent $event): void
    {
        $reader = new ConfigReader($event->getConfiguration());
        $this->indexManager->deleteAllIndices($reader->getName());
    }

    /**
     * @throws \RuntimeException
     * @throws ESClientException
     */
    public function onPostSave(ConfigurationEvent $event): void
    {
        $reader = new ConfigReader($event->getConfiguration());

        // Handle asset indices
        if ($reader->isAssetIndexingEnabled()) {
            $this->handleAssetIndices($reader);
        }

        // Handle object indices
        if ($reader->isObjectIndexingEnabled()) {
            $this->handleObjectIndices($reader);
        }

        // Initialize endpoint
        $this->initializeEndpoint($reader);
    }

    private function handleAssetIndices(ConfigReader $reader): void
    {
        $endpointName = $reader->getName();

        // Asset Folders
        $this->indexManager->createOrUpdateIndex(
            $this->indexManager->getIndexName(IndexManager::INDEX_ASSET_FOLDER, $endpointName),
            $this->folderMapping->generate()
        );

        // Assets
        $this->indexManager->createOrUpdateIndex(
            $this->indexManager->getIndexName(IndexManager::INDEX_ASSET, $endpointName),
            $this->assetMapping->generate($reader->toArray())
        );
    }

    private function handleObjectIndices(ConfigReader $reader): void
    {
        $endpointName = $reader->getName();

        // DataObject Folders
        $this->indexManager->createOrUpdateIndex(
            $this->indexManager->getIndexName(IndexManager::INDEX_OBJECT_FOLDER, $endpointName),
            $this->folderMapping->generate()
        );

        $objectClasses = $reader->getObjectClasses();

        // DataObject Classes
        foreach ($objectClasses as $class) {
            $this->indexManager->createOrUpdateIndex(
                $this->indexManager->getIndexName(mb_strtolower($class['name']), $endpointName),
                $this->objectMapping->generate($class)
            );
        }
    }

    /**
     * @throws \RuntimeException
     * @throws ESClientException
     */
    private function initializeEndpoint(ConfigReader $reader): void
    {
        $indices = $this->indexManager->getAllIndexNames($reader);

        // Clear index data
        foreach ($indices as $index) {
            $this->indexManager->clearIndexData($index);
        }

        $this->messageBus->dispatch(new InitializeEndpointMessage($reader->getName()));
    }
}
