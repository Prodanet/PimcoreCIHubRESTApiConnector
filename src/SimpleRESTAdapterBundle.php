<?php
/**
 * Simple REST Adapter.
 *
 * LICENSE
 *
 * This source file is subject to the GNU General Public License version 3 (GPLv3)
 * For the full copyright and license information, please view the LICENSE.md and gpl-3.0.txt
 * files that are distributed with this source code.
 *
 * @copyright  Copyright (c) 2021 CI HUB GmbH (https://ci-hub.com)
 * @license    https://github.com/ci-hub-gmbh/SimpleRESTAdapterBundle/blob/master/gpl-3.0.txt GNU General Public License version 3 (GPLv3)
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

    public const PACKAGE_NAME = 'ci-hub/rest-adapter-bundle';
    public function getInstaller(): ?InstallerInterface
    {
        return $this->container->get(Installer::class);
    }
    /**
     * {@inheritdoc}
     */
    public function getCssPaths(): array
    {
        return [
            '/bundles/simplerestadapter/pimcore/css/icons.css',
        ];
    }

    /**
     * {@inheritdoc}
     */
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

    /**
     * {@inheritdoc}
     */
    protected function getComposerPackageName(): string
    {
        return self::PACKAGE_NAME;
    }
}
