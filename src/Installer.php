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

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Schema;
use Pimcore\Db;
use Pimcore\Extension\Bundle\Installer\SettingsStoreAwareInstaller;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

/**
 * Class Installer.
 */
class Installer extends SettingsStoreAwareInstaller
{
    final public const USER_PERMISSIONS = ['plugin_datahub_adapter'];

    protected ?Schema $schema = null;

    private array $tablesToInstall = [
        'users_datahub_config' => 'CREATE TABLE `users_datahub_config` (
                `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `userid` int(11) UNSIGNED NOT NULL,
                `data` text NOT NULL,
                PRIMARY KEY (`id`),
                FOREIGN KEY (userid) REFERENCES users(id)
                    on update cascade on delete cascade
            );',
        'datahub_upload_sessions' => 'CREATE TABLE `datahub_upload_sessions` (
                `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `parts` json NOT NULL,
                `totalParts` int(20) NOT NULL,
                `fileSize` int(20) NOT NULL,
                `assetId` int(20) UNSIGNED NOT NULL,
                `parentId` int(20) UNSIGNED NOT NULL,
                `fileName` varchar(700) NOT NULL,
                PRIMARY KEY (`id`),
                FOREIGN KEY (assetId) REFERENCES assets(id)
                    on update cascade on delete cascade
            );',
    ];

    public function __construct(
        protected BundleInterface $bundle,
        protected Connection $db
    ) {
        parent::__construct($bundle);
    }

    /**
     * @throws Exception
     */
    protected function addPermissions(): void
    {
        $db = Db::get();

        foreach (self::USER_PERMISSIONS as $permission) {
            $db->insert('users_permission_definitions', [
                $db->quoteIdentifier('key') => $permission,
                $db->quoteIdentifier('category') => \Pimcore\Bundle\DataHubBundle\Installer::DATAHUB_PERMISSION_CATEGORY,
            ]);
        }
    }

    /**
     * @throws Exception
     */
    protected function removePermissions(): void
    {
        $db = Db::get();

        foreach (self::USER_PERMISSIONS as $permission) {
            $db->delete('users_permission_definitions', [
                $db->quoteIdentifier('key') => $permission,
            ]);
        }
    }

    public function canBeInstalled(): bool
    {
        return true;
    }

    /**
     * @throws Exception
     */
    private function installTables(): void
    {
        foreach ($this->tablesToInstall as $name => $statement) {
            if ($this->getSchema()->hasTable($name)) {
                $this->output->write(sprintf(
                    '     <comment>WARNING:</comment> Skipping table "%s" as it already exists',
                    $name
                ));

                continue;
            }

            $this->db->executeQuery($statement);
        }
    }

    /**
     * @throws Exception
     */
    private function uninstallTables(): void
    {
        foreach (array_keys($this->tablesToInstall) as $table) {
            if (!$this->getSchema()->hasTable($table)) {
                $this->output->write(sprintf(
                    '     <comment>WARNING:</comment> Not dropping table "%s" as it doesn\'t exist',
                    $table
                ));

                continue;
            }

            $this->db->executeQuery("DROP TABLE IF EXISTS $table");
        }
    }

    /**
     * @throws Exception
     */
    public function install(): void
    {
        $this->addPermissions();
        $this->installTables();
        parent::install();
    }

    /**
     * @throws Exception
     */
    public function uninstall(): void
    {
        $this->removePermissions();
        $this->uninstallTables();

        parent::uninstall();
    }

    /**
     * @throws Exception
     */
    protected function getSchema(): Schema
    {
        return $this->schema ??= $this->db->createSchemaManager()->introspectSchema();
    }
}
