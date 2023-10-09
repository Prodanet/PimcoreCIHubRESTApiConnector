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

namespace CIHub\Bundle\SimpleRESTAdapterBundle;

use Pimcore\Bundle\DataHubBundle\PimcoreDataHubBundle;
use Pimcore\Db;
use Pimcore\Extension\Bundle\Installer\Exception\InstallationException;
use Pimcore\Extension\Bundle\Installer\SettingsStoreAwareInstaller;
use Pimcore\Model\User\Permission\Definition;

/**
 * Class Installer.
 */
final class Installer extends SettingsStoreAwareInstaller
{
    public const PERMISSION_KEY = 'plugin_datahub_adapter';

    public const USER_DATAHUB_CONFIG_TABLE = 'users_datahub_config';

    public const UPLOAD_SESSION_TABLE = 'datahub_upload_sessions';

    public function needsReloadAfterInstall(): bool
    {
        return true;
    }

    /**
     * @throws \Exception
     */
    public function install(): void
    {
        if (!PimcoreDataHubBundle::isInstalled()) {
            throw new InstallationException('Install PimcoreDataHubBundle first');
        }
        // create backend permission
        Definition::create(self::PERMISSION_KEY)->setCategory(\Pimcore\Bundle\DataHubBundle\Installer::DATAHUB_PERMISSION_CATEGORY)->save();
        $connection = Db::get();
        if (method_exists($connection, 'getSchemaManager')) {
            $schema = $connection->getSchemaManager()->createSchema();
            $currentSchema = $connection->getSchemaManager()->createSchema();
        } else {
            $schema = $connection->createSchemaManager()->introspectSchema();
            $currentSchema = $connection->createSchemaManager()->createSchema();
        }

        // create table
        if (!$schema->hasTable(self::USER_DATAHUB_CONFIG_TABLE)) {
            $tableConfig = $schema->createTable(self::USER_DATAHUB_CONFIG_TABLE);
            $tableConfig->addColumn('id', 'integer', ['length' => 11, 'autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
            $tableConfig->addColumn('data', 'text', ['notnull' => false]);
            $tableConfig->addColumn('userId', 'integer', ['length' => 11, 'notnull' => true, 'unsigned' => true]);
            $tableConfig->setPrimaryKey(['id']);
            $tableConfig->addForeignKeyConstraint(
                'users',
                ['userId'],
                ['id'],
                ['onDelete' => 'CASCADE']
            );
        }

        // create table
        if (!$schema->hasTable(self::UPLOAD_SESSION_TABLE)) {
            $tableSession = $schema->createTable(self::UPLOAD_SESSION_TABLE);
            $tableSession->addColumn('id', 'string', ['length' => 255, 'notnull' => false]);
            $tableSession->addUniqueIndex(['id']);
            $tableSession->addColumn('parts', 'json', ['notnull' => false, 'default' => json_encode([])]);
            $tableSession->addColumn('totalParts', 'integer', ['length' => 11, 'notnull' => false]);
            $tableSession->addColumn('fileSize', 'integer', ['length' => 11, 'notnull' => false]);
            $tableSession->addColumn('assetId', 'integer', ['length' => 11, 'notnull' => false]);
            $tableSession->addColumn('parentId', 'integer', ['length' => 11, 'notnull' => false]);
            $tableSession->addColumn('fileName', 'string', ['length' => 700, 'notnull' => true]);
            $tableSession->setPrimaryKey(['id']);
        }

        $sqlStatements = $currentSchema->getMigrateToSql($schema, $connection->getDatabasePlatform()); // @phpstan-ignore-line
        if (!empty($sqlStatements)) {
            $connection->exec(implode(';', $sqlStatements));
        }

        parent::install();
    }

    public function uninstall(): void
    {
        $connection = Db::get();
        if (method_exists($connection, 'getSchemaManager')) {
            $schema = $connection->getSchemaManager()->createSchema();
            $currentSchema = $connection->getSchemaManager()->createSchema();
        } else {
            $schema = $connection->createSchemaManager()->introspectSchema();
            $currentSchema = $connection->createSchemaManager()->createSchema();
        }

        if ($schema->hasTable(self::USER_DATAHUB_CONFIG_TABLE)) {
            $schema->dropTable(self::USER_DATAHUB_CONFIG_TABLE);
        }

        if ($schema->hasTable(self::UPLOAD_SESSION_TABLE)) {
            $schema->dropTable(self::UPLOAD_SESSION_TABLE);
        }

        $sqlStatements = $currentSchema->getMigrateToSql($schema, $connection->getDatabasePlatform()); // @phpstan-ignore-line
        if (!empty($sqlStatements)) {
            $connection->exec(implode(';', $sqlStatements));
        }
    }

    public function isInstalled(): bool
    {
        $connection = Db::get();
        if (method_exists($connection, 'getSchemaManager')) {
            $schema = $connection->getSchemaManager()->createSchema();
        } else {
            $schema = $connection->createSchemaManager()->introspectSchema();
        }

        return $schema->hasTable(self::USER_DATAHUB_CONFIG_TABLE) && $schema->hasTable(self::UPLOAD_SESSION_TABLE);
    }

    public function canBeInstalled(): bool
    {
        return !$this->isInstalled();
    }

    public function canBeUninstalled(): bool
    {
        return $this->isInstalled();
    }
}
