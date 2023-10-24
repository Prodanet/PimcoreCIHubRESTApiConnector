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

use CIHub\Bundle\SimpleRESTAdapterBundle\Exception\ConfigurationNotFoundException;
use CIHub\Bundle\SimpleRESTAdapterBundle\Exception\InvalidParameterException;
use CIHub\Bundle\SimpleRESTAdapterBundle\Extractor\LabelExtractorInterface;
use CIHub\Bundle\SimpleRESTAdapterBundle\Manager\AuthManager;
use CIHub\Bundle\SimpleRESTAdapterBundle\Provider\AssetProvider;
use CIHub\Bundle\SimpleRESTAdapterBundle\Provider\DataObjectProvider;
use CIHub\Bundle\SimpleRESTAdapterBundle\Reader\ConfigReader;
use CIHub\Bundle\SimpleRESTAdapterBundle\Repository\DataHubConfigurationRepository;
use Doctrine\DBAL\Exception;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\TermsAggregation;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\FullText\SimpleQueryStringQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery;
use ONGR\ElasticsearchDSL\Search;
use ONGR\ElasticsearchDSL\Sort\FieldSort;
use Pimcore\Bundle\DataHubBundle\Configuration;
use Pimcore\Controller\FrontendController;
use Pimcore\Model\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

abstract class BaseEndpointController extends FrontendController
{
    protected const OPERATOR_MAP = [
        '$and' => BoolQuery::MUST,
        '$not' => BoolQuery::MUST_NOT,
        '$or' => BoolQuery::SHOULD,
    ];

    protected string $config;

    protected bool $includeAggregations = false;

    protected int $nextPageCursor = 200;

    protected Request $request;

    protected User $user;

    /**
     * @throws Exception
     */
    public function __construct(
        private DataHubConfigurationRepository $dataHubConfigurationRepository,
        private LabelExtractorInterface $labelExtractor,
        private RequestStack $requestStack,
        protected AuthManager $authManager,
        private AssetProvider $assetProvider,
        private DataObjectProvider $dataObjectProvider,
    ) {
        $this->request = $this->requestStack->getMainRequest();
        $this->config = $this->request->get('config');
        $this->user = $this->authManager->authenticate();
    }

    public function getAssetProvider(): AssetProvider
    {
        return $this->assetProvider;
    }

    public function getDataObjectProvider(): DataObjectProvider
    {
        return $this->dataObjectProvider;
    }

    public function applySearchSettings(Search $search): void
    {
        $size = (int) $this->request->get('size', 200);
        $pageCursor = (int) $this->request->get('page_cursor', 0);
        $orderBy = $this->request->get('order_by');

        $search->setSize($size);
        $search->setFrom($pageCursor);

        if (null !== $orderBy) {
            $search->addSort(new FieldSort($orderBy));
        }

        $this->nextPageCursor = $pageCursor + $size;
    }

    /**
     * @throws \JsonException
     */
    protected function applyQueriesAndAggregations(Search $search, ConfigReader $configReader): void
    {
        $parentId = (int) $this->request->get('parent_id', 1);
        $type = $this->request->get('type', 'object');
        $orderBy = $this->request->get('order_by', null);
        $fulltext = $this->request->get('fulltext_search');
        /*
         * @TODO to remove on 2.2.x
         */
        if ($this->request->query->has('filter')) {
            $filter = json_decode($this->request->get('filter'), true, 512, \JSON_THROW_ON_ERROR);
        } else {
            $filter = [];
        }

        $this->includeAggregations = filter_var(
            $this->request->get('include_aggs', false),
            \FILTER_VALIDATE_BOOLEAN
        );

        if (!empty($fulltext)) {
            $search->addQuery(new SimpleQueryStringQuery($fulltext));
        }

        if (\is_array($filter) && [] !== $filter) {
            $this->buildQueryConditions($search, $filter);
        }

        if ($this->includeAggregations) {
            $labels = $configReader->getLabelSettings();

            foreach ($labels as $label) {
                if (!isset($label['useInAggs']) || !$label['useInAggs']) {
                    continue;
                }

                $field = $label['id'];
                $search->addAggregation(new TermsAggregation($field, $field));
            }
        }

        $query['bool']['filter']['bool']['must'][] = [
            'term' => [
                'system.type' => $type,
            ],
        ];
        $query['bool']['filter']['bool']['must'][] = [
            'term' => [
                'system.parentId' => $parentId,
            ],
        ];

        $body['query'] = $query;

        $sort = [];

        if ($orderBy) {
            foreach ($orderBy as $field => $order) {
                $sort[] = [
                    $field => [
                        'order' => $order,
                        'missing' => '_last',
                        'unmapped_type' => 'keyword',
                    ],
                ];
            }
        }

        $sort[] = [
            'system.id' => [
                'order' => 'asc',
            ],
        ];
        $body['sort'] = $sort;
    }

    /**
     * @param array<string, string|array> $filters
     */
    protected function buildQueryConditions(Search $search, array $filters): void
    {
        foreach ($filters as $key => $value) {
            if (\array_key_exists(mb_strtolower($key), self::OPERATOR_MAP)) {
                $operator = self::OPERATOR_MAP[mb_strtolower($key)];

                if (!\is_array($value)) {
                    continue;
                }

                foreach ($value as $condition) {
                    if (!\is_array($condition)) {
                        continue;
                    }

                    $field = (string) array_key_first($condition);
                    $search->addQuery(new TermQuery($field, $condition[$field]), $operator);
                }
            } elseif (\is_array($value)) {
                foreach ($value as $subKey => $subValue) {
                    if (\array_key_exists(mb_strtolower($subKey), self::OPERATOR_MAP)) {
                        $subOperator = self::OPERATOR_MAP[mb_strtolower($subKey)];

                        if (BoolQuery::MUST_NOT !== $subOperator) {
                            continue;
                        }

                        $search->addQuery(new TermQuery($key, $subValue), $subOperator);
                    }
                }
            } else {
                $search->addQuery(new TermQuery($key, $value));
            }
        }
    }

    /**
     * @param array<string, string|array> $result
     *
     * @return array<string, string|array>
     */
    protected function buildResponse(array $result, ConfigReader $configReader): array
    {
        $response = [];

        if (isset($result['hits']['hits'])) {
            $hitIndices = [];
            $items = [];
            foreach ($result['hits']['hits'] as $hit) {
                if (!\in_array($hit['_index'], $hitIndices, true)) {
                    $hitIndices[] = $hit['_index'];
                }

                $items[] = $hit['_source'];
            }

            $response = [
                'total_count' => $result['hits']['total']['value'] ?? 0,
                'items' => $items,
            ];

            if ($response['total_count'] > 0) {
                // Page Cursor
                $response['page_cursor'] = $this->nextPageCursor;

                // Aggregations
                if ($this->includeAggregations) {
                    $aggs = [];
                    $aggregations = $result['aggregations'] ?? [];

                    foreach ($aggregations as $field => $aggregation) {
                        if (empty($aggregation['buckets'])) {
                            continue;
                        }

                        $aggs[$field]['buckets'] = array_map(static fn (array $bucket): array => [
                            'key' => $bucket['key'],
                            'element_count' => $bucket['doc_count'],
                        ], $aggregation['buckets']);
                    }

                    $response['aggregations'] = $aggs;
                }

                // Labels
                $labels = $this->labelExtractor->extractLabels($hitIndices);
                $response['labels'] = $configReader->filterLabelSettings($labels);
            }
        } elseif (isset($result['_index'], $result['_source'])) {
            $response = $result['_source'];

            // Labels
            $labels = $this->labelExtractor->extractLabels([$result['_index']]);
            $response['labels'] = $configReader->filterLabelSettings($labels);
        }

        return $response;
    }

    /**
     * @param array<string, string|null> $params
     */
    protected function checkRequiredParameters(array $params): void
    {
        $required = [];

        foreach ($params as $key => $value) {
            if (!empty($value)) {
                continue;
            }

            $required[] = $key;
        }

        if ([] !== $required) {
            throw new InvalidParameterException($required);
        }
    }

    protected function getDataHubConfiguration(): Configuration
    {
        $configuration = $this->dataHubConfigurationRepository->findOneByName($this->config);

        if (!$configuration instanceof Configuration) {
            throw new ConfigurationNotFoundException($this->config);
        }

        return $configuration;
    }
}
