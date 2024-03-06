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

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Controller;

use CIHub\Bundle\SimpleRESTAdapterBundle\Elasticsearch\Index\IndexQueryService;
use CIHub\Bundle\SimpleRESTAdapterBundle\Exception\InvalidParameterException;
use CIHub\Bundle\SimpleRESTAdapterBundle\Exception\NotFoundException;
use CIHub\Bundle\SimpleRESTAdapterBundle\Manager\IndexManager;
use CIHub\Bundle\SimpleRESTAdapterBundle\Reader\ConfigReader;
use CIHub\Bundle\SimpleRESTAdapterBundle\Traits\ListingFilterTrait;
use CIHub\Bundle\SimpleRESTAdapterBundle\Traits\RestHelperTrait;
use Nelmio\ApiDocBundle\Annotation\Security;
use ONGR\ElasticsearchDSL\Query\FullText\MatchQuery;
use OpenApi\Attributes as OA;
use Pimcore\Model\Asset\Listing;
use Pimcore\Model\DataObject;
use Pimcore\Model\Element\Service;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route(path: ['/datahub/rest/{config}', '/pimcore-datahub-webservices/simplerest/{config}'], name: 'datahub_rest_endpoints_')]
#[Security(name: 'Bearer')]
#[OA\Tag(name: 'Search')]
final class SearchController extends BaseEndpointController
{
    use ListingFilterTrait;
    use RestHelperTrait;

    /**
     * @throws \Exception
     */
    #[Route('/search', name: 'search', methods: ['GET'])]
    #[OA\Get(
        description: 'Method to search for elements, returns elements of all types. For paging use link provided in link header of response.',
        summary: 'Search for elements',
        parameters: [
            new OA\Parameter(
                name: 'Authorization',
                description: 'Bearer (in Swagger UI use authorize feature to set header)',
                in: 'header'
            ),
            new OA\Parameter(
                name: 'config',
                description: 'Name of the config.',
                in: 'path',
                required: true,
                schema: new OA\Schema(
                    type: 'string'
                )
            ),
            new OA\Parameter(
                name: 'size',
                description: 'Max items of response, default 200.',
                in: 'query',
                required: true,
                schema: new OA\Schema(
                    type: 'integer',
                    default: 200
                )
            ),
            new OA\Parameter(
                name: 'fulltext_search',
                description: 'Search term for fulltext search.',
                in: 'query',
                required: false,
                schema: new OA\Schema(
                    type: 'string'
                )
            ),
            new OA\Parameter(
                name: 'filter',
                description: 'Define filter for further filtering. See https://restdb.io/docs/querying-with-the-api for filter syntax, implemeted operators are $not, $or, $and.',
                in: 'query',
                required: false,
                schema: new OA\Schema(
                    type: 'string'
                )
            ),
            new OA\Parameter(
                name: 'order_by',
                description: 'Field(s) to order by.',
                in: 'query',
                required: false,
                schema: new OA\Schema(
                    type: 'string'
                ),
                examples: [new OA\Examples('system.id', ''), new OA\Examples('{"system.id": "acs"}', ''), new OA\Examples('{"system.subtype": "asc", "system.id":"desc"}', '')]
            ),
            new OA\Parameter(
                name: 'page_cursor',
                description: 'Page cursor for paging. Use page cursor of link header in last response.',
                in: 'query',
                required: false,
                schema: new OA\Schema(
                    type: 'string'
                )
            ),
            new OA\Parameter(
                name: 'include_aggs',
                description: 'Set to true to include aggregation information, default false.',
                in: 'query',
                required: false,
                schema: new OA\Schema(
                    type: 'boolean',
                    default: false
                )
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'total_count',
                            description: 'Total count of available results.',
                            type: 'integer'
                        ),
                        new OA\Property(
                            property: 'items',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(
                                        property: 'system',
                                        type: 'array',
                                        items: new OA\Items(
                                            properties: [
                                                new OA\Property(
                                                    property: 'id',
                                                    type: 'integer',
                                                ),
                                                new OA\Property(
                                                    property: 'key',
                                                    type: 'string',
                                                ),
                                                new OA\Property(
                                                    property: 'fullPath',
                                                    type: 'string',
                                                ),
                                                new OA\Property(
                                                    property: 'type',
                                                    type: 'string',
                                                ),
                                                new OA\Property(
                                                    property: 'locked',
                                                    type: 'boolean',
                                                ),
                                                new OA\Property(
                                                    property: 'parentId',
                                                    type: 'integer',
                                                ),
                                                new OA\Property(
                                                    property: 'hasChildren',
                                                    type: 'boolean',
                                                ),
                                                new OA\Property(
                                                    property: 'creationDate',
                                                    type: 'string',
                                                ),
                                                new OA\Property(
                                                    property: 'modificationDate',
                                                    type: 'string',
                                                ),
                                                new OA\Property(
                                                    property: 'subtype',
                                                    type: 'string',
                                                ),
                                            ],
                                            type: 'object',
                                        ),
                                    ),
                                    new OA\Property(
                                        property: 'metaData',
                                        type: 'array',
                                        items: new OA\Items()
                                    ),
                                    new OA\Property(
                                        property: 'dimensionData',
                                        type: 'array',
                                        items: new OA\Items(
                                            properties: [
                                                new OA\Property(
                                                    property: 'width',
                                                    type: 'integer',
                                                ),
                                                new OA\Property(
                                                    property: 'height',
                                                    type: 'integer',
                                                ),
                                            ],
                                            type: 'object',
                                        )
                                    ),
                                    new OA\Property(
                                        property: 'xmpData',
                                        type: 'array',
                                        items: new OA\Items()
                                    ),
                                    new OA\Property(
                                        property: 'exifData',
                                        type: 'array',
                                        items: new OA\Items()
                                    ),
                                    new OA\Property(
                                        property: 'iptcData',
                                        type: 'array',
                                        items: new OA\Items()
                                    ),
                                    new OA\Property(
                                        property: 'binaryData',
                                        type: 'array',
                                        items: new OA\Items(
                                            properties: [
                                                new OA\Property(
                                                    property: 'path',
                                                    type: 'string',
                                                ),
                                                new OA\Property(
                                                    property: 'checksum',
                                                    type: 'string',
                                                ),
                                                new OA\Property(
                                                    property: 'filename',
                                                    type: 'string',
                                                ),
                                            ],
                                            type: 'object',
                                            uniqueItems: true
                                        )
                                    ),
                                ],
                                type: 'object',
                            )
                        ),
                        new OA\Property(
                            property: 'page_cursor',
                            description: 'Page cursor for next page.',
                            type: 'string'
                        ),
                    ],
                    type: 'object'
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
            ),
        ],
    )]
    public function searchAction(Request $request, IndexManager $indexManager, IndexQueryService $indexService): JsonResponse
    {
        // Check if request is authenticated properly
        $this->authManager->checkAuthentication();
        $configuration = $this->getDataHubConfiguration();
        $configReader = new ConfigReader($configuration->getConfiguration());

        $indices = [];

        if ($configReader->isAssetIndexingEnabled()) {
            $indices = [$indexManager->getIndexName(IndexManager::INDEX_ASSET, $this->config)];
        }

        if ($configReader->isObjectIndexingEnabled()) {
            $indices = array_merge(
                $indices,
                array_map(fn ($className): string => $indexManager->getIndexName(mb_strtolower($className), $this->config), $configReader->getObjectClassNames())
            );
        }
        if (empty($indices)) {
            throw new \InvalidArgumentException('There is no index configured at all.');
        }
        $search = $indexService->createSearch();
        $this->applySearchSettings($search);
        $this->applyQueriesAndAggregations($search, $configReader);

        $result = $indexService->search(implode(',', $indices), $search->toArray());

        return $this->json($this->buildResponse($result, $configReader));
    }

    /**
     * @throws \Exception
     */
    #[Route('/tree-items', name: 'tree_items', methods: ['GET'])]
    #[OA\Get(
        description: 'Method to load all elements of a tree level. For paging use link provided in link header of response.',
        summary: 'Get tree items',
        parameters: [
            new OA\Parameter(
                name: 'Authorization',
                description: 'Bearer (in Swagger UI use authorize feature to set header)',
                in: 'header'
            ),
            new OA\Parameter(
                name: 'config',
                description: 'Name of the config.',
                in: 'path',
                required: true,
                schema: new OA\Schema(
                    type: 'string'
                )
            ),
            new OA\Parameter(
                name: 'type',
                description: 'Type of elements – asset or object.',
                in: 'query',
                required: true,
                schema: new OA\Schema(
                    type: 'string',
                    enum: ['asset', 'object']
                )
            ),
            new OA\Parameter(
                name: 'parentId',
                description: 'ID of parent element.',
                in: 'query',
                required: false,
                schema: new OA\Schema(
                    type: 'integer'
                )
            ),
            new OA\Parameter(
                name: 'size',
                description: 'Max items of response, default 200.',
                in: 'query',
                required: false,
                schema: new OA\Schema(
                    type: 'integer',
                    default: 200
                )
            ),
            new OA\Parameter(
                name: 'fulltext_search',
                description: 'Search term for fulltext search.',
                in: 'query',
                required: false,
                schema: new OA\Schema(
                    type: 'string'
                )
            ),
            new OA\Parameter(
                name: 'filter',
                description: 'Define filter for further filtering. See https://pimcore.com/docs/pimcore/current/Development_Documentation/Web_Services/Query_Filters.html for filter syntax, implemeted operators are $not, $or, $and.',
                in: 'query',
                required: false,
                schema: new OA\Schema(
                    type: 'string'
                )
            ), new OA\Parameter(
                name: 'order_by',
                description: 'Field to order by.',
                in: 'query',
                required: false,
                schema: new OA\Schema(
                    type: 'string'
                )
            ),
            new OA\Parameter(
                name: 'page_cursor',
                description: 'Page cursor for paging. Use page cursor of link header in last response.',
                in: 'query',
                required: false,
                schema: new OA\Schema(
                    type: 'string'
                )
            ),
            new OA\Parameter(
                name: 'include_folders',
                description: 'Set to true to include folders, default false.',
                in: 'query',
                required: false,
                schema: new OA\Schema(
                    type: 'boolean',
                    default: false
                )
            ),
            new OA\Parameter(
                name: 'include_aggs',
                description: 'Set to true to include aggregation information, default false.',
                in: 'query',
                required: false,
                schema: new OA\Schema(
                    type: 'boolean',
                    default: false
                )
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'total_count',
                            description: 'Total count of available results.',
                            type: 'integer'
                        ),
                        new OA\Property(
                            property: 'items',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(
                                        property: 'system',
                                        type: 'array',
                                        items: new OA\Items(
                                            properties: [
                                                new OA\Property(
                                                    property: 'id',
                                                    type: 'integer',
                                                ),
                                                new OA\Property(
                                                    property: 'key',
                                                    type: 'string',
                                                ),
                                                new OA\Property(
                                                    property: 'fullPath',
                                                    type: 'string',
                                                ),
                                                new OA\Property(
                                                    property: 'type',
                                                    type: 'string',
                                                ),
                                                new OA\Property(
                                                    property: 'locked',
                                                    type: 'boolean',
                                                ),
                                                new OA\Property(
                                                    property: 'parentId',
                                                    type: 'integer',
                                                ),
                                                new OA\Property(
                                                    property: 'hasChildren',
                                                    type: 'boolean',
                                                ),
                                                new OA\Property(
                                                    property: 'creationDate',
                                                    type: 'string',
                                                ),
                                                new OA\Property(
                                                    property: 'modificationDate',
                                                    type: 'string',
                                                ),
                                                new OA\Property(
                                                    property: 'subtype',
                                                    type: 'string',
                                                ),
                                            ]
                                        ),
                                    ),
                                ],
                                type: 'object'
                            )
                        ),
                        new OA\Property(
                            property: 'page_cursor',
                            description: 'Page cursor for next page.',
                            type: 'string'
                        ),
                        new OA\Property(
                            property: 'next',
                            description: 'Link to next page. Use this link for paging.',
                            type: 'string',
                            nullable: true
                        ),
                        new OA\Property(
                            property: 'prev',
                            description: 'Link to previous page. Use this link for paging.',
                            type: 'string',
                            nullable: true
                        ),
                    ],
                    type: 'object'
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
            ),
        ],
    )]
    public function treeItemsAction(IndexManager $indexManager, IndexQueryService $indexService): JsonResponse
    {
        $this->authManager->checkAuthentication();
        $configuration = $this->getDataHubConfiguration();
        $configReader = new ConfigReader($configuration->getConfiguration());

        $id = 1;
        if ($this->request->query->has('parentId')) {
            $id = $this->request->query->getInt('parentId');
        }
        $includeFolders = filter_var(
            $this->request->get('include_folders', true),
            \FILTER_VALIDATE_BOOLEAN
        );

        $type = $this->request->query->getString('type');
        $this->checkRequiredParameters(['type' => $type]);

        $root = Service::getElementById($type, $id);
        if (!$root) {
            throw new NotFoundException(sprintf("Parent with id [%s] doesn't exist for the type [%s]", $id, $type));
        }
        if (!$root->isAllowed('list', $this->user)) {
            throw new AccessDeniedHttpException('Missing the permission to list in the folder: '.$root->getRealFullPath());
        }

        $indices = [];

        if ('asset' === $type && $configReader->isAssetIndexingEnabled()) {
            $indices = [$indexManager->getIndexName(IndexManager::INDEX_ASSET, $this->config)];

            if (true === $includeFolders) {
                $indices[] = $indexManager->getIndexName(IndexManager::INDEX_ASSET_FOLDER, $this->config);
            }
        } elseif ('object' === $type && $configReader->isObjectIndexingEnabled()) {
            $indices = array_map(function ($className) use ($indexManager) {
                return $indexManager->getIndexName(mb_strtolower($className), $this->config);
            }, $configReader->getObjectClassNames());

            if (true === $includeFolders) {
                $indices[] = $indexManager->getIndexName(IndexManager::INDEX_OBJECT_FOLDER, $this->config);
            }
        }

        if (empty($indices)) {
            throw new \InvalidArgumentException(sprintf('There is no index configured for %s', $type));
        }
        $search = $indexService->createSearch();
        $this->applySearchSettings($search);
        $this->applyQueriesAndAggregations($search, $configReader);
        $search->addQuery(new MatchQuery('system.parentId', $root->getId()));

        $result = $indexService->search(implode(',', $indices), $search->toArray());

        return $this->json($this->buildResponse($result, $configReader));
        //
        //        $size = $this->request->query->getInt('size', 200);
        //        $orderBy = $this->request->query->getString('order_by', 'id');
        //        $pageCursor = $this->request->query->get('page_cursor', null);
        //
        //        $result = [];
        //        $childrenList = match ($type) {
        //            'asset' => new Listing(),
        //            'object' => new DataObject\Listing(),
        //            default => throw new InvalidParameterException('Type ['.$type.'] is not supported'),
        //        };
        //
        //        $childrenList->addConditionParam('parentId = ?', [$root->getId()]);
        //
        //        $conditionParam = $this->filterAccessibleByUser($this->user, $root);
        //        if ($conditionParam) {
        //            $childrenList = $childrenList->addConditionParam($conditionParam);
        //        }
        //
        //        $childrenList->setOrderKey($orderBy);
        //
        //        $pageCursor = null == $pageCursor ? 0 : $pageCursor;
        //        foreach ($childrenList->getItems($pageCursor, $size) as $child) {
        //            $result[] = $this->getChild($child, $configReader);
        //        }
        //
        //        $response = [];
        //        if (0 === $pageCursor) {
        //            $pageCursor += $size;
        //        } else {
        //            $pageCursor = $size;
        //        }
        //
        //        if (\count($result) == $size) {
        //            $requestParameters = $this->request->query->all();
        //            $requestParameters['config'] = $this->request->get('config');
        //            if ($pageCursor < $childrenList->getTotalCount()) {
        //                $requestParameters['page_cursor'] = $pageCursor;
        //                $response['next'] = $this->generateUrl('datahub_rest_endpoints_tree_items', $requestParameters, UrlGeneratorInterface::ABSOLUTE_URL);
        //            }
        //
        //            $requestParameters['page_cursor'] = ($pageCursor - ($size * 2));
        //            if ($requestParameters['page_cursor'] > 0) {
        //                $response['prev'] = $this->generateUrl('datahub_rest_endpoints_tree_items', $requestParameters, UrlGeneratorInterface::ABSOLUTE_URL);
        //            }
        //        }
        //
        //        $response = array_merge([
        //            'total_count' => $childrenList->getTotalCount(),
        //            'items' => $result,
        //            'page_cursor' => $pageCursor,
        //        ], $response);
        //
        //        return new JsonResponse($response);
    }
}
