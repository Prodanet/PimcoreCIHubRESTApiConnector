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
use CIHub\Bundle\SimpleRESTAdapterBundle\Manager\IndexManager;
use CIHub\Bundle\SimpleRESTAdapterBundle\Messenger\RebuildIndexElementMessage;
use CIHub\Bundle\SimpleRESTAdapterBundle\Reader\ConfigReader;
use Doctrine\DBAL\Exception;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Symfony\Component\Messenger\MessageBusInterface;

readonly class EndpointAndIndexesConfigurator
{
    public function __construct(
        private IndexManager $indexManager,
        private MessageBusInterface $messageBus,
        private AssetMapping $assetMapping,
        private DataObjectMapping $dataObjectMapping,
        private FolderMapping $folderMapping,
    ) {
    }

    /**
     * @throws Exception
     */
    public function createOrUpdate(ConfigReader $configReader): void
    {
        if ($configReader->isAssetIndexingEnabled()) {
            try {
                $this->handleAssetIndices($configReader);
            } catch (ClientResponseException|MissingParameterException|ServerResponseException $e) {
            }
        }

        if ($configReader->isObjectIndexingEnabled()) {
            try {
                $this->handleObjectIndices($configReader);
            } catch (ClientResponseException|MissingParameterException|ServerResponseException $e) {
            }
        }

        $this->initIndex($configReader);
    }

    /**
     * @throws Exception
     */
    protected function initIndex(ConfigReader $configReader): void
    {
        $this->messageBus->dispatch(new RebuildIndexElementMessage($configReader->getName(), $configReader));
    }

    /**
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     */
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

    /**
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     */
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
                $this->indexManager->getIndexName(mb_strtolower((string) $objectClass['name']), $endpointName),
                $this->dataObjectMapping->generate($objectClass)
            );
        }
    }
}
