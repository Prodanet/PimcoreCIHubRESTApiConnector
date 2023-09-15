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

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Messenger\Handler;

use CIHub\Bundle\SimpleRESTAdapterBundle\Elasticsearch\Index\IndexPersistenceService;
use CIHub\Bundle\SimpleRESTAdapterBundle\Manager\IndexManager;
use CIHub\Bundle\SimpleRESTAdapterBundle\Messenger\UpdateIndexElementMessage;
use Exception;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject;
use Pimcore\Model\Element\ElementInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

final class UpdateIndexElementMessageHandler implements MessageHandlerInterface
{
    /**
     * @var IndexManager
     */
    private IndexManager $indexManager;

    /**
     * @var IndexPersistenceService
     */
    private IndexPersistenceService $indexService;

    /**
     * @param IndexManager            $indexManager
     * @param IndexPersistenceService $indexService
     */
    public function __construct(IndexManager $indexManager, IndexPersistenceService $indexService)
    {
        $this->indexManager = $indexManager;
        $this->indexService = $indexService;
    }

    /**
     * @param UpdateIndexElementMessage $message
     * @throws Exception
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
