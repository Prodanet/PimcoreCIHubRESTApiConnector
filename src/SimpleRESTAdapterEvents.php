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

final class SimpleRESTAdapterEvents
{
    /**
     * The CONFIGURATION_PRE_DELETE event occurs before the configuration gets deleted.
     *
     * @Event("CIHub\Bundle\SimpleRESTAdapterBundle\Model\Event\ConfigurationEvent")
     */
    public const CONFIGURATION_PRE_DELETE = 'datahub.rest.configuration.pre_delete';

    /**
     * The CONFIGURATION_POST_DELETE event occurs after the configuration was deleted.
     *
     * @Event("CIHub\Bundle\SimpleRESTAdapterBundle\Model\Event\ConfigurationEvent")
     */
    public const CONFIGURATION_POST_DELETE = 'datahub.rest.configuration.post_delete';

    /**
     * The CONFIGURATION_PRE_SAVE event occurs before the configuration gets saved.
     *
     * @Event("CIHub\Bundle\SimpleRESTAdapterBundle\Model\Event\GetModifiedConfigurationEvent")
     */
    public const CONFIGURATION_PRE_SAVE = 'datahub.rest.configuration.pre_save';

    /**
     * The CONFIGURATION_POST_SAVE event occurs after the configuration was saved.
     *
     * @Event("CIHub\Bundle\SimpleRESTAdapterBundle\Model\Event\ConfigurationEvent")
     */
    public const CONFIGURATION_POST_SAVE = 'datahub.rest.configuration.post_save';
}
