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
    const TABLE = 'users_datahub_config';

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
        } else {
            $schema = $db->createSchemaManager()->introspectSchema();
        }


        // create table
        if ($schema->hasTable(self::TABLE)) {
            $table = $schema->getTable(self::TABLE);
            $table->addColumn('id', 'int', ['length' => 1, 'autoincrement' => true, 'notnull' => true]);
            $table->addColumn('data', 'text', ['notnull' => false]);
            $table->addColumn('userId', 'int', ['length' => 1, 'notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addForeignKeyConstraint(
                'users',
                ['userId'],
                ['id'],
                ['onDelete' => 'CASCADE']
            );
        }
    }
}
