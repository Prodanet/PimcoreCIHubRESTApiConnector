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
declare(strict_types=1);

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Elasticsearch;

use CIHub\Bundle\SimpleRESTAdapterBundle\Elasticsearch\Mapping\AssetMapping;
use CIHub\Bundle\SimpleRESTAdapterBundle\Elasticsearch\Mapping\DataObjectMapping;
use CIHub\Bundle\SimpleRESTAdapterBundle\Elasticsearch\Mapping\FolderMapping;
use CIHub\Bundle\SimpleRESTAdapterBundle\Exception\ESClientException;
use CIHub\Bundle\SimpleRESTAdapterBundle\Manager\IndexManager;
use CIHub\Bundle\SimpleRESTAdapterBundle\Messenger\InitializeEndpointMessage;
use CIHub\Bundle\SimpleRESTAdapterBundle\Messenger\RebuildIndexElementMessage;
use CIHub\Bundle\SimpleRESTAdapterBundle\Messenger\UpdateIndexElementMessage;
use CIHub\Bundle\SimpleRESTAdapterBundle\Reader\ConfigReader;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Pimcore\Db;
use Pimcore\Model\Asset;
use Pimcore\Model\Asset\Folder;
use Pimcore\Model\DataObject;
use Pimcore\Model\Element\ElementInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class EndpointAndIndexesConfigurator
{
    public function __construct(
        private IndexManager $indexManager,
        private MessageBusInterface $messageBus,
        private AssetMapping $assetMapping,
        private DataObjectMapping $dataObjectMapping,
        private FolderMapping $folderMapping
    ) {
    }

    /**
     * @throws Exception
     */
    public function createOrUpdate(ConfigReader $configReader): void
    {
        if ($configReader->isAssetIndexingEnabled()) {
            $this->handleAssetIndices($configReader);
        }

        if ($configReader->isObjectIndexingEnabled()) {
            $this->handleObjectIndices($configReader);
        }

        $this->initializeEndpoint($configReader);
        $this->initIndex($configReader);
    }

    /**
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws ServerResponseException
     * @throws ESClientException
     */
    private function initializeEndpoint(ConfigReader $configReader): void
    {
        $indices = $this->indexManager->getAllIndexNames($configReader);

        foreach ($indices as $index) {
            $this->indexManager->clearIndexData($index);
        }

        $this->messageBus->dispatch(new InitializeEndpointMessage($configReader->getName()));
    }

    /**
     * @throws Exception
     */
    protected function initIndex(ConfigReader $configReader): void
    {
        if ($configReader->isAssetIndexingEnabled()) {
            $this->messageBus->dispatch(new RebuildIndexElementMessage($configReader->getName()));
        }
    }

    protected function getDb(): Connection
    {
        return Db::get();
    }

    private function enqueueParentFolders(
        ?ElementInterface $element,
        string $folderClass,
        string $type,
        string $name
    ): void {
        while ($element instanceof $folderClass && 1 !== $element->getId()) {
            $this->messageBus->dispatch(new UpdateIndexElementMessage($element->getId(), $type, $name));
            $element = $element->getParent();
        }
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
}
