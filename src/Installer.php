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

use Pimcore\Db;
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

    /**
     * @throws \Exception
     */
    public function install(): void
    {
        parent::install();
        // create backend permission
        Definition::create(self::PERMISSION_KEY)->setCategory(\Pimcore\Bundle\DataHubBundle\Installer::DATAHUB_PERMISSION_CATEGORY)->save();
        $connection = Db::get();

        if (method_exists($connection, 'getSchemaManager')) {
            $schema = $connection->getSchemaManager()->createSchema();
        } else {
            $schema = $connection->createSchemaManager()->introspectSchema();
        }

        // create table
        if (!$schema->hasTable(self::USER_DATAHUB_CONFIG_TABLE)) {
            $table = $schema->createTable(self::USER_DATAHUB_CONFIG_TABLE);
            $table->addColumn('id', 'integer', ['length' => 11, 'autoincrement' => true, 'notnull' => true]);
            $table->addColumn('data', 'text', ['notnull' => false]);
            $table->addColumn('userId', 'integer', ['length' => 11, 'notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addForeignKeyConstraint(
                'users',
                ['userId'],
                ['id'],
                ['onDelete' => 'CASCADE'],
                'fk_datahub_users'
            );
        }

        // create table
        if (!$schema->hasTable(self::UPLOAD_SESSION_TABLE)) {
            $table = $schema->createTable(self::UPLOAD_SESSION_TABLE);
            $table->addColumn('id', 'string', ['length' => 255, 'notnull' => false]);
            $table->addUniqueIndex(['id']);
            $table->addColumn('parts', 'json', ['notnull' => false, 'default' => json_encode([])]);
            $table->addColumn('totalParts', 'integer', ['length' => 11, 'notnull' => false]);
            $table->addColumn('fileSize', 'integer', ['length' => 11, 'notnull' => false]);
            $table->addColumn('assetId', 'integer', ['length' => 11, 'notnull' => false]);
            $table->addColumn('parentId', 'integer', ['length' => 11, 'notnull' => false]);
            $table->addColumn('fileName', 'string', ['length' => 700, 'notnull' => true]);
            $table->setPrimaryKey(['id']);
        }
    }
}
