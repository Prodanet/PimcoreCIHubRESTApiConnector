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
use Nelmio\ApiDocBundle\Annotation\Security;
use ONGR\ElasticsearchDSL\Query\FullText\MatchQuery;
use OpenApi\Attributes as OA;
use Pimcore\Model\Element\Service;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route("/datahub/rest/{config}")]
#[Route("/pimcore-datahub-webservices/simplerest/{config}")]
#[Security(name: "Bearer")]
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
    #[Route("/search", name: "datahub_rest_adapter_endpoints_search", methods: ["GET"])]
    #[OA\Get(
        description: 'Method to search for elements, returns elements of all types. For paging use link provided in link header of response.',
        parameters: [
            new OA\Parameter(
                name: 'Authorization',
                in: 'header',
                description: 'Bearer (in Swagger UI use authorize feature to set header)'
            ),
            new OA\Parameter(
                name: 'config',
                in: 'path',
                description: 'Name of the config.',
                required: true,
                schema: new OA\Schema(
                    type: 'string'
                )
            ),
            new OA\Parameter(
                name: 'size',
                in: 'query',
                description: 'Max items of response, default 200.',
                required: true,
                schema: new OA\Schema(
                    type: 'integer',
                    default: 200
                )
            ),
            new OA\Parameter(
                name: 'fulltext_search',
                in: 'query',
                description: 'Search term for fulltext search.',
                required: false,
                schema: new OA\Schema(
                    type: 'string'
                )
            ),
            new OA\Parameter(
                name: 'filter',
                in: 'query',
                description: 'Define filter for further filtering. See https://pimcore.com/docs/pimcore/current/Development_Documentation/Web_Services/Query_Filters.html for filter syntax, implemeted operators are $not, $or, $and.',
                required: false,
                schema: new OA\Schema(
                    type: 'string'
                )
            ),
            new OA\Parameter(
                name: 'order_by',
                in: 'query',
                description: 'Field to order by.',
                required: false,
                schema: new OA\Schema(
                    type: 'string'
                )
            ),
            new OA\Parameter(
                name: 'page_cursor',
                in: 'query',
                description: 'Page cursor for paging. Use page cursor of link header in last response.',
                required: false,
                schema: new OA\Schema(
                    type: 'string'
                )
            ),
            new OA\Parameter(
                name: 'include_aggs',
                in: 'query',
                description: 'Set to true to include aggregation information, default false.',
                required: false,
                schema: new OA\Schema(
                    type: 'boolean',
                    default: false
                )
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation.',
                content: new OA\JsonContent (
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'total_count',
                            description: 'Total count of available results.',
                            type: 'integer'
                        ),
                        new OA\Property(
                            property: 'items',
                            type: 'array',
                            items: new OA\Items()
                        ),
                        new OA\Property(
                            property: 'page_cursor',
                            type: 'string',
                            description: 'Page cursor for next page.'
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Not found'
            ),
            new OA\Response(
                response: 401,
                description: 'Access denied'
            ),
            new OA\Response(
                response: 500,
                description: 'Server error'
            )
        ],
    )]
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
    #[Route("/tree-items", name: "datahub_rest_adapter_endpoints_tree_items", methods: ["GET"])]
    #[OA\Get(
        description: 'Method to load all elements of a tree level. For paging use link provided in link header of response.',
        parameters: [
            new OA\Parameter(
                name: 'Authorization',
                in: 'header',
                description: 'Bearer (in Swagger UI use authorize feature to set header)'
            ),
            new OA\Parameter(
                name: 'config',
                in: 'path',
                description: 'Name of the config.',
                required: true,
                schema: new OA\Schema(
                    type: 'string'
                )
            ),
            new OA\Parameter(
                name: 'type',
                in: 'query',
                description: 'Type of elements – asset or object.',
                required: true,
                schema: new OA\Schema(
                    type: 'string',
                    enum: ["asset", "object"]
                )
            ),
            new OA\Parameter(
                name: 'parent_id',
                in: 'query',
                description: 'ID of parent element.',
                required: false,
                schema: new OA\Schema(
                    type: 'integer'
                )
            ),
            new OA\Parameter(
                name: 'include_folders',
                in: 'query',
                description: 'Define if folders should be included, default true.',
                required: false,
                schema: new OA\Schema(
                    type: 'bool',
                    default: true
                )
            ),
            new OA\Parameter(
                name: 'size',
                in: 'query',
                description: 'Max items of response, default 200.',
                required: false,
                schema: new OA\Schema(
                    type: 'integer',
                    default: 200
                )
            ),
            new OA\Parameter(
                name: 'fulltext_search',
                in: 'query',
                description: 'Search term for fulltext search.',
                required: false,
                schema: new OA\Schema(
                    type: 'string'
                )
            ),
            new OA\Parameter(
                name: 'filter',
                in: 'query',
                description: 'Define filter for further filtering. See https://pimcore.com/docs/pimcore/current/Development_Documentation/Web_Services/Query_Filters.html for filter syntax, implemeted operators are $not, $or, $and.',
                required: false,
                schema: new OA\Schema(
                    type: 'string'
                )
            ), new OA\Parameter(
                name: 'order_by',
                in: 'query',
                description: 'Field to order by.',
                required: false,
                schema: new OA\Schema(
                    type: 'string'
                )
            ),
            new OA\Parameter(
                name: 'page_cursor',
                in: 'query',
                description: 'Page cursor for paging. Use page cursor of link header in last response.',
                required: false,
                schema: new OA\Schema(
                    type: 'string'
                )
            ),
            new OA\Parameter(
                name: 'include_aggs',
                in: 'query',
                description: 'Set to true to include aggregation information, default false.',
                required: false,
                schema: new OA\Schema(
                    type: 'boolean',
                    default: false
                )
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation.',
                content: new OA\JsonContent (
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'total_count',
                            description: 'Total count of available results.',
                            type: 'integer'
                        ),
                        new OA\Property(
                            property: 'items',
                            type: 'array',
                            items: new OA\Items()
                        ),
                        new OA\Property(
                            property: 'page_cursor',
                            type: 'string',
                            description: 'Page cursor for next page.'
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Not found'
            ),
            new OA\Response(
                response: 401,
                description: 'Access denied'
            ),
            new OA\Response(
                response: 500,
                description: 'Server error'
            )
        ],
    )]
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
