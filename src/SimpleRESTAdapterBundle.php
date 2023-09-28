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

use Pimcore\Extension\Bundle\AbstractPimcoreBundle;
use Pimcore\Extension\Bundle\Installer\InstallerInterface;
use Pimcore\Extension\Bundle\PimcoreBundleAdminClassicInterface;
use Pimcore\Extension\Bundle\Traits\BundleAdminClassicTrait;
use Pimcore\Extension\Bundle\Traits\PackageVersionTrait;

class SimpleRESTAdapterBundle extends AbstractPimcoreBundle implements PimcoreBundleAdminClassicInterface
{
    use BundleAdminClassicTrait;
    use PackageVersionTrait;

    final public const PACKAGE_NAME = 'ci-hub/rest-adapter-bundle';

    public function getInstaller(): ?InstallerInterface
    {
        return $this->container->get(Installer::class);
    }

    public function getCssPaths(): array
    {
        return [
            '/bundles/simplerestadapter/pimcore/css/icons.css',
        ];
    }

    public function getJsPaths(): array
    {
        return [
            '/bundles/simplerestadapter/pimcore/js/adapter.js',
            '/bundles/simplerestadapter/pimcore/js/config-item.js',
            '/bundles/simplerestadapter/pimcore/js/grid-config-dialog.js',
            '/bundles/simplerestadapter/pimcore/js/userTab.js',
            '/bundles/simplerestadapter/pimcore/js/user/ciHub.js',
        ];
    }

    protected function getComposerPackageName(): string
    {
        return self::PACKAGE_NAME;
    }
}
