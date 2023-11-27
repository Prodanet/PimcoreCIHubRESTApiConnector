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

namespace CIHub\Bundle\SimpleRESTAdapterBundle\EventListener;

use CIHub\Bundle\SimpleRESTAdapterBundle\Elasticsearch\EndpointAndIndexesConfigurator;
use CIHub\Bundle\SimpleRESTAdapterBundle\Exception\ESClientException;
use CIHub\Bundle\SimpleRESTAdapterBundle\Manager\IndexManager;
use CIHub\Bundle\SimpleRESTAdapterBundle\Model\Event\ConfigurationEvent;
use CIHub\Bundle\SimpleRESTAdapterBundle\Reader\ConfigReader;
use CIHub\Bundle\SimpleRESTAdapterBundle\SimpleRESTAdapterEvents;
use Doctrine\DBAL\Exception;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class ConfigModificationListener implements EventSubscriberInterface
{
    public function __construct(
        private IndexManager $indexManager,
        private EndpointAndIndexesConfigurator $endpointAndIndexesConfigurator
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SimpleRESTAdapterEvents::CONFIGURATION_PRE_DELETE => 'onPreDelete',
            SimpleRESTAdapterEvents::CONFIGURATION_POST_SAVE => 'onPostSave',
        ];
    }

    public function onPreDelete(ConfigurationEvent $configurationEvent): void
    {
        $configReader = new ConfigReader($configurationEvent->getConfiguration());
        $this->indexManager->deleteAllIndices($configReader->getName());
    }

    /**
     * @throws \RuntimeException
     * @throws ESClientException
     * @throws Exception
     */
    public function onPostSave(ConfigurationEvent $configurationEvent): void
    {
        $configReader = new ConfigReader($configurationEvent->getConfiguration());

        $this->endpointAndIndexesConfigurator->createOrUpdate($configReader);
    }
}
