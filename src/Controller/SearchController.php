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

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Controller;

use CIHub\Bundle\SimpleRESTAdapterBundle\Elasticsearch\Index\IndexQueryService;
use CIHub\Bundle\SimpleRESTAdapterBundle\Manager\IndexManager;
use CIHub\Bundle\SimpleRESTAdapterBundle\Reader\ConfigReader;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Exception;
use ONGR\ElasticsearchDSL\Query\FullText\MatchQuery;
use Pimcore\Model\Element\Service;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route("/pimcore-datahub-webservices/simplerest/{config}")]
class SearchController extends BaseEndpointController
{
    /**
     * @param Request $request
     * @param IndexManager $indexManager
     * @param IndexQueryService $indexService
     *
     * @return JsonResponse
     * @throws Exception
     */
    #[Route("/search", name: "simple_rest_adapter_endpoints_search", methods: ["GET"])]
    public function searchAction(Request $request, IndexManager $indexManager, IndexQueryService $indexService): JsonResponse
    {
        // Check if request is authenticated properly
        $this->authManager->checkAuthentication();
        $configuration = $this->getDataHubConfiguration();
        $reader = new ConfigReader($configuration->getConfiguration());
        $size = $this->request->get('size', 200);
        $pageCursor = $this->request->get('page_cursor', null);
        $pageCursor = $pageCursor ?: 0;

        $indices = [];

        if ($reader->isAssetIndexingEnabled()) {
            $indices = [$indexManager->getIndexName(IndexManager::INDEX_ASSET, $this->config)];
        }

        if ($reader->isObjectIndexingEnabled()) {
            $indices = array_merge(
                $indices,
                array_map(function ($className) use ($indexManager) {
                    return $indexManager->getIndexName(strtolower($className), $this->config);
                }, $reader->getObjectClassNames())
            );
        }

        $search = $indexService->createSearch($request);
        $this->applySearchSettings($search);
        $this->applyQueriesAndAggregations($search, $reader);

        $search->setSize($size);
        $search->setFrom($pageCursor * $size);

        $result = $indexService->search(implode(',', $indices), $search->toArray());

        return $this->json($this->buildResponse($result, $reader));
    }

    /**
     * @param Request $request
     * @param IndexManager $indexManager
     * @param IndexQueryService $indexService
     *
     * @return JsonResponse
     * @throws \Doctrine\DBAL\Exception
     * @throws ClientResponseException
     * @throws ServerResponseException
     */
    #[Route("/tree-items", name: "simple_rest_adapter_endpoints_tree_items", methods: ["GET"])]
    public function treeItemsAction(Request $request, IndexManager $indexManager, IndexQueryService $indexService): JsonResponse
    {
        // Check if request is authenticated properly
        $this->authManager->checkAuthentication();
        $configuration = $this->getDataHubConfiguration();
        $reader = new ConfigReader($configuration->getConfiguration());

        $id = 1;
        if ($request->get('id')) {
            $id = (int)$request->get('id');
        }
        $type = $this->request->get('type');
        // Check if required parameters are missing
        $this->checkRequiredParameters(['type' => $type]);

        $root = Service::getElementById($type, $id);
        if (!$root->isAllowed('list')) {
            throw new AccessDeniedHttpException(
                'Missing the permission to list in the folder: ' . $root->getRealFullPath()
            );
        }

        $parentId = $this->request->get('parent_id', '1');
        $includeFolders = filter_var(
            $this->request->get('include_folders', true),
            FILTER_VALIDATE_BOOLEAN
        );

        $indices = [];

        if ('asset' === $type && $reader->isAssetIndexingEnabled()) {
            $indices = [$indexManager->getIndexName(IndexManager::INDEX_ASSET, $this->config)];

            if (true === $includeFolders) {
                $indices[] = $indexManager->getIndexName(IndexManager::INDEX_ASSET_FOLDER, $this->config);
            }
        } elseif ('object' === $type && $reader->isObjectIndexingEnabled()) {
            $indices = array_map(function ($className) use ($indexManager) {
                return $indexManager->getIndexName(strtolower($className), $this->config);
            }, $reader->getObjectClassNames());

            if (true === $includeFolders) {
                $indices[] = $indexManager->getIndexName(IndexManager::INDEX_OBJECT_FOLDER, $this->config);
            }
        }

        $search = $indexService->createSearch($request);
        $this->applySearchSettings($search);
        $this->applyQueriesAndAggregations($search, $reader);
        $search->addQuery(new MatchQuery('system.parentId', $parentId));

        $result = $indexService->search(implode(',', $indices), $search->toArray());

        return $this->json($this->buildResponse($result, $reader));
    }
}
