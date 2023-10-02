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

final class ConfigModificationListener implements EventSubscriberInterface
{
    public function __construct(private IndexManager $indexManager, private MessageBusInterface $messageBus, private AssetMapping $assetMapping, private DataObjectMapping $dataObjectMapping, private FolderMapping $folderMapping)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SimpleRESTAdapterEvents::CONFIGURATION_PRE_DELETE => 'onPreDelete',
            SimpleRESTAdapterEvents::CONFIGURATION_POST_SAVE => 'onPostSave',
        ];
    }

    public function onPreDelete(ConfigurationEvent $configurationEvent): void
    {
        $configReader = new ConfigReader($configurationEvent->getConfiguration());
        $this->indexManager->deleteAllIndices($configReader->getName());
    }

    /**
     * @throws \RuntimeException
     * @throws ESClientException
     */
    public function onPostSave(ConfigurationEvent $configurationEvent): void
    {
        $configReader = new ConfigReader($configurationEvent->getConfiguration());

        // Handle asset indices
        if ($configReader->isAssetIndexingEnabled()) {
            $this->handleAssetIndices($configReader);
        }

        // Handle object indices
        if ($configReader->isObjectIndexingEnabled()) {
            $this->handleObjectIndices($configReader);
        }

        // Initialize endpoint
        $this->initializeEndpoint($configReader);
    }

    private function handleAssetIndices(ConfigReader $configReader): void
    {
        $endpointName = $configReader->getName();

        // Asset Folders
        $this->indexManager->createOrUpdateIndex(
            $this->indexManager->getIndexName(IndexManager::INDEX_ASSET_FOLDER, $endpointName),
            $this->folderMapping->generate()
        );

        // Assets
        $this->indexManager->createOrUpdateIndex(
            $this->indexManager->getIndexName(IndexManager::INDEX_ASSET, $endpointName),
            $this->assetMapping->generate($configReader->toArray())
        );
    }

    private function handleObjectIndices(ConfigReader $configReader): void
    {
        $endpointName = $configReader->getName();

        // DataObject Folders
        $this->indexManager->createOrUpdateIndex(
            $this->indexManager->getIndexName(IndexManager::INDEX_OBJECT_FOLDER, $endpointName),
            $this->folderMapping->generate()
        );

        $objectClasses = $configReader->getObjectClasses();

        // DataObject Classes
        foreach ($objectClasses as $objectClass) {
            $this->indexManager->createOrUpdateIndex(
                $this->indexManager->getIndexName(mb_strtolower($objectClass['name']), $endpointName),
                $this->dataObjectMapping->generate($objectClass)
            );
        }
    }

    /**
     * @throws \RuntimeException
     * @throws ESClientException
     */
    private function initializeEndpoint(ConfigReader $configReader): void
    {
        $indices = $this->indexManager->getAllIndexNames($configReader);

        // Clear index data
        foreach ($indices as $index) {
            $this->indexManager->clearIndexData($index);
        }

        $this->messageBus->dispatch(new InitializeEndpointMessage($configReader->getName()));
    }
}
