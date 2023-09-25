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

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Messenger\Handler;

use CIHub\Bundle\SimpleRESTAdapterBundle\Elasticsearch\Index\IndexPersistenceService;
use CIHub\Bundle\SimpleRESTAdapterBundle\Manager\IndexManager;
use CIHub\Bundle\SimpleRESTAdapterBundle\Messenger\UpdateIndexElementMessage;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject;
use Pimcore\Model\Element\ElementInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

final class UpdateIndexElementMessageHandler implements MessageHandlerInterface
{
    private IndexManager $indexManager;

    private IndexPersistenceService $indexService;

    public function __construct(IndexManager $indexManager, IndexPersistenceService $indexService)
    {
        $this->indexManager = $indexManager;
        $this->indexService = $indexService;
    }

    /**
     * @throws \Exception
     */
    public function __invoke(UpdateIndexElementMessage $message): void
    {
        $element = match ($message->getEntityType()) {
            'asset' => Asset::getById($message->getEntityId()),
            'object' => DataObject\AbstractObject::getById($message->getEntityId()),
            default => null,
        };

        if (!$element instanceof ElementInterface) {
            return;
        }

        $this->indexService->update(
            $element,
            $message->getEndpointName(),
            $this->indexManager->getIndexName($element, $message->getEndpointName())
        );
    }
}
