<?php
namespace CIHub\Bundle\SimpleRESTAdapterBundle;

use Exception;
use Pimcore\Db;
use Pimcore\Extension\Bundle\Installer\SettingsStoreAwareInstaller;
use Pimcore\Model\User\Permission\Definition;

/**
 * Class Installer
 * @package CIHub\Bundle\SimpleRESTAdapterBundle
 */
class Installer extends SettingsStoreAwareInstaller
{
    const PERMISSION_KEY = 'plugin_datahub_adapter';
    const CONFIG_TABLE = 'users_datahub_config';
    const UPLOAD_SESSION_TABLE = 'datahub_upload_sessions';

    /**
     * @throws Exception
     */
    public function install(): void
    {
        parent::install();
        // create backend permission
        Definition::create(self::PERMISSION_KEY)->setCategory(\Pimcore\Bundle\DataHubBundle\Installer::DATAHUB_PERMISSION_CATEGORY)->save();
        $db = Db::get();

        if (method_exists($db, 'getSchemaManager')) {
            $schema = $db->getSchemaManager()->createSchema();
            $currentSchema = $db->getSchemaManager()->createSchema();
        } else {
            $schema = $db->createSchemaManager()->introspectSchema();
            $currentSchema = $db->createSchemaManager()->introspectSchema();
        }


        // create table
        if (!$schema->hasTable(self::CONFIG_TABLE)) {
            $table = $schema->createTable(self::CONFIG_TABLE);
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

        $sqlStatements = $currentSchema->getMigrateToSql($schema, $db->getDatabasePlatform());
        if (!empty($sqlStatements)) {
            $db->executeStatement(implode(';', $sqlStatements));
        }
    }
}
