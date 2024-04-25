<?php

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Command;


use CIHub\Bundle\SimpleRESTAdapterBundle\Elasticsearch\Index\IndexPersistenceService;
use CIHub\Bundle\SimpleRESTAdapterBundle\Exception\NotFoundException;
use CIHub\Bundle\SimpleRESTAdapterBundle\Manager\IndexManager;
use CIHub\Bundle\SimpleRESTAdapterBundle\Reader\ConfigReader;
use CIHub\Bundle\SimpleRESTAdapterBundle\Repository\DataHubConfigurationRepository;
use Doctrine\DBAL\Exception;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Pimcore\Bundle\DataHubBundle\Configuration;
use Pimcore\Db;
use Pimcore\Logger;
use Pimcore\Model\Asset;
use Pimcore\Model\Asset\Folder;
use Pimcore\Model\DataObject;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\Version;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('datahub:index:rebuild')]
class RebuildIndexCommand extends Command
{
    public const CHUNK_SIZE = 100;

    public const TYPE_ASSET = 'asset';
    public const TYPE_OBJECT = 'object';

    public function __construct(
        private readonly IndexManager                   $indexManager,
        private readonly IndexPersistenceService        $indexPersistenceService,
        private readonly DataHubConfigurationRepository $dataHubConfigurationRepository,
    ) {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this
            ->setDescription('Rebuild index')
            ->addArgument(
                'name', InputArgument::REQUIRED, 'Specify configuration name',
            )
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $endpointName = $input->getArgument('name');
        $output->writeln('Starting index rebuilding for configuration: '.$endpointName);

        try {
            $configuration = $this->dataHubConfigurationRepository->findOneByName($endpointName);

            if ($configuration instanceof Configuration) {

                $configReader = new ConfigReader($configuration->getConfiguration());

                $this->cleanAliases($configReader);

                if ($configReader->isAssetIndexingEnabled()) {
                    $this->rebuildType(self::TYPE_ASSET, $endpointName, $output);
                }
                if ($configReader->isObjectIndexingEnabled()) {
                    $this->rebuildType(self::TYPE_OBJECT, $endpointName, $output);
                }
            }
        } catch (\Exception $e) {
            Logger::crit($e->getMessage());
        }

        return Command::SUCCESS;;
    }

    /**
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     */
    public function cleanAliases(ConfigReader $configReader): void
    {
        $indices = $this->indexManager->getAllIndexNames($configReader);
        foreach ($indices as $alias) {
            $index = $this->indexManager->findIndexNameByAlias($alias);
            $newIndexName = $this->getNewIndexName($index);
            if ($this->indexPersistenceService->indexExists($newIndexName)) {
                $this->indexPersistenceService->deleteIndex($newIndexName);
            }
            $mapping = $this->indexPersistenceService->getMapping($index)[$index]['mappings'];
            $this->indexPersistenceService->createIndex($newIndexName, $mapping);
        }
    }

    /**
     * @throws Exception
     */
    private function rebuildType(string $type, string $endpointName, OutputInterface &$output): void
    {
        $conn = Db::getConnection();
        $sql = "SELECT id, parentId FROM {$type}s";

        $totalRecords = $conn->executeQuery("SELECT COUNT(id) FROM {$type}s")->fetchNumeric()[0];
        // Calculate the number of batches
        $batchSize = self::CHUNK_SIZE;
        $totalBatches = ceil($totalRecords / $batchSize);
        // Fetch records in batches
        for ($i = 0; $i < $totalBatches; $i++) {
            $offset = $i * $batchSize;

            // Add LIMIT and OFFSET to the query
            $batchQuery = $sql . " LIMIT $batchSize OFFSET $offset";

            // Execute the query and fetch results
            $stmt = $conn->executeQuery($batchQuery);
            $batchResults = $stmt->fetchAllAssociative();

            // Process the batch results here
            foreach ($batchResults as $result) {
                $id = (int)$result['id'];
                $element = match ($type) {
                    'asset' => Asset::getById($id),
                    'object' => DataObject::getById($id),
                    'version' => Version::getById($id),
                    default => throw new NotFoundException($type.' with id ['.$id."] doesn't exist"),
                };
                $elementType = $element instanceof Asset ? 'asset' : 'object';
                try {
                    $output->writeln(sprintf("Indexing element %s (%s)", $elementType, $id));
                    $this->indexPersistenceService->update(
                        $element,
                        $endpointName,
                        $this->indexManager->getIndexName($element, $endpointName)
                    );
                    $this->enqueueParentFolders(
                        $element->getParent(),
                        $elementType === 'asset'? Folder::class : DataObject\Folder::class,
                        $endpointName
                    );

                } catch (\Exception $e) {
                    Logger::crit($e->getMessage());
                    $output->writeln("Error: " . $e->getMessage());
                }

                $element = null;
                unset($element);
            }

            // Free the statement resources
            $stmt->free();
        }
    }

    private function enqueueParentFolders(
        ?ElementInterface $element,
        string $folderClass,
        string $name
    ): void {
        while ($element instanceof $folderClass && 1 !== $element->getId()) {
            try {
                $this->indexPersistenceService->update(
                    $element,
                    $name,
                    $this->indexManager->getIndexName($element, $name)
                );
            } catch (\Exception $e) {
                Logger::crit($e->getMessage());
            }
            $element = $element->getParent();
        }
    }

    public function getNewIndexName(string $index): string
    {
        return str_ends_with($index, '-odd') ? str_replace('-odd', '', $index).'-even' : str_replace('-even', '', $index).'-odd';
    }
}
