<?php
namespace CIHub\Bundle\SimpleRESTAdapterBundle;

use Pimcore\Db;
use Pimcore\Extension\Bundle\Installer\Exception\InstallationException;
use Pimcore\Extension\Bundle\Installer\SettingsStoreAwareInstaller;
use Pimcore\Logger;

class Installer extends SettingsStoreAwareInstaller
{
    public function install(): void
    {
        try {
            $db = Db::get();
            $db->executeQuery("CREATE TABLE IF NOT EXISTS users_datahub_config (
                 id     int(11) unsigned auto_increment,
                 data   text null,
                 userId int(11) unsigned not null,
                 primary key (id),
                 foreign key (userId) references users (id)
            );");
        } catch (\Exception $e) {
            Logger::warn($e);
            throw new InstallationException($e->getMessage());
        }

        parent::install();
    }
}
