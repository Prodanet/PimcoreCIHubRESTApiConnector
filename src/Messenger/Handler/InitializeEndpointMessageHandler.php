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

use CIHub\Bundle\SimpleRESTAdapterBundle\Messenger\InitializeEndpointMessage;
use CIHub\Bundle\SimpleRESTAdapterBundle\Messenger\UpdateIndexElementMessage;
use CIHub\Bundle\SimpleRESTAdapterBundle\Reader\ConfigReader;
use CIHub\Bundle\SimpleRESTAdapterBundle\Repository\DataHubConfigurationRepository;
use CIHub\Bundle\SimpleRESTAdapterBundle\Utils\WorkspaceSorter;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\Statement;
use Pimcore\Bundle\DataHubBundle\Configuration;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class InitializeEndpointMessageHandler implements MessageHandlerInterface
{
    private const CONDITION_DISTINCT = 'distinct';
    private const CONDITION_INCLUSIVE = 'inclusive';
    private const CONDITION_EXCLUSIVE = 'exclusive';

    /**
     * @var array<string, array>
     */
    private array $conditions = [];

    /**
     * @var array<string, string>
     */
    private array $params = [];

    public function __construct(private DataHubConfigurationRepository $configRepository, private Connection $connection, private MessageBusInterface $messageBus)
    {
    }

    public function __invoke(InitializeEndpointMessage $message): void
    {
        $endpointName = $message->getEndpointName();
        $configuration = $this->configRepository->findOneByName($endpointName);

        if (!$configuration instanceof Configuration) {
            return;
        }

        $reader = new ConfigReader($configuration->getConfiguration());

        // Initialize assets
        if ($reader->isAssetIndexingEnabled()) {
            $workspace = WorkspaceSorter::sort($reader->getWorkspace('asset'));
            $this->buildConditions($workspace, 'filename', 'path');

            if (isset($this->conditions[self::CONDITION_INCLUSIVE]) && $this->params !== []) {
                $ids = $this->fetchIdsFromDatabaseTable('assets', 'id');

                foreach ($ids as $id) {
                    $this->messageBus->dispatch(
                        new UpdateIndexElementMessage($id, 'asset', $endpointName)
                    );
                }
            }

            // Reset conditions and params
            $this->conditions = $this->params = [];
        }

        // Initialize objects
        if ($reader->isObjectIndexingEnabled()) {
            $workspace = WorkspaceSorter::sort($reader->getWorkspace('object'));
            $this->buildConditions($workspace, 'o_key', 'o_path');

            if (isset($this->conditions[self::CONDITION_INCLUSIVE]) && $this->params !== []) {
                $ids = $this->fetchIdsFromDatabaseTable('objects', 'o_id');

                foreach ($ids as $id) {
                    $this->messageBus->dispatch(
                        new UpdateIndexElementMessage($id, 'object', $endpointName)
                    );
                }
            }
        }
    }

    /**
     * Builds the conditions for database query.
     *
     * @param array<int, array> $workspace
     */
    private function buildConditions(array $workspace, string $keyColumn, string $pathColumn): void
    {
        foreach ($workspace as $item) {
            $read = $item['read'];
            $path = $item['cpath'];
            $pathParts = explode('/', $path);

            // If not root folder, add distinct conditions
            if (\count($pathParts) > 2 || '' !== $pathParts[1]) {
                $this->addDistinctConditions($pathParts, $keyColumn, $pathColumn);
            }

            // Always add the ex-/inclusive conditions
            $pathIndex = uniqid('path_', false);
            $this->conditions[$read ? self::CONDITION_INCLUSIVE : self::CONDITION_EXCLUSIVE][] = sprintf(
                '%s %s :%s',
                $pathColumn,
                $read ? 'LIKE' : 'NOT LIKE',
                $pathIndex
            );
            $this->params[$pathIndex] = rtrim($path, '/').'/%';
        }
    }

    /**
     * Builds the conditions for distinct elements.
     *
     * @param array<int, string> $pathParts
     */
    private function addDistinctConditions(array $pathParts, string $keyColumn, string $pathColumn): void
    {
        $keyIndex = uniqid('key_', false);
        $keyPathIndex = uniqid('key_path_', false);
        $keyParam = array_pop($pathParts);
        $keyPathParam = implode('/', $pathParts).'/';

        if (!\in_array($keyParam, $this->params, true)) {
            $this->conditions[self::CONDITION_DISTINCT][] = sprintf(
                '(%s = :%s AND %s = :%s)',
                $keyColumn,
                $keyIndex,
                $pathColumn,
                $keyPathIndex
            );

            $this->params[$keyIndex] = $keyParam;
            $this->params[$keyPathIndex] = $keyPathParam;
        }

        // Add parent folders to distinct conditions as well
        if (\count($pathParts) > 1) {
            $this->addDistinctConditions($pathParts, $keyColumn, $pathColumn);
        }
    }

    /**
     * Runs the database query and returns found ID's.
     *
     * @return array<int, mixed>
     */
    private function fetchIdsFromDatabaseTable(string $from, string $select): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select($select)
            ->from($from)
            ->where(implode(' OR ', $this->conditions[self::CONDITION_INCLUSIVE]))
            ->setParameters($this->params);

        if (isset($this->conditions[self::CONDITION_DISTINCT])) {
            $qb->orWhere(implode(' OR ', $this->conditions[self::CONDITION_DISTINCT]));
        }

        if (isset($this->conditions[self::CONDITION_EXCLUSIVE])) {
            $qb->andWhere(implode(' OR ', $this->conditions[self::CONDITION_EXCLUSIVE]));
        }

        try {
            $ids = $qb->fetchFirstColumn();
        } catch (\Exception) {
            $ids = [];
        }

        return $ids;
    }
}
