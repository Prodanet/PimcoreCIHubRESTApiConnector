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

namespace CIHub\Bundle\SimpleRESTAdapterBundle\Elasticsearch\Index;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use ONGR\ElasticsearchDSL\Search;

final readonly class IndexQueryService
{
    public function __construct(private Client $client, private string $indexNamePrefix)
    {
    }

    public function createSearch(): Search
    {
        return new Search();
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ClientResponseException
     * @throws MissingParameterException
     * @throws ServerResponseException
     */
    public function get(int $id, string $index): array
    {
        $params = [
            'id' => $id,
            'index' => $index,
        ];

        return $this->client->get($params)->asArray();
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws \Exception
     */
    public function search(string $index, array $query, array $params = []): array
    {
        if (str_ends_with($index, '*')) {
            $index = sprintf('%s__%s', $this->indexNamePrefix, ltrim($index, '_'));
        }

        $requestParams = [
            'track_total_hits' => true,
            'rest_total_hits_as_int' => true,
            'index' => explode(',', $index),
            'body' => $query,
        ];

        if ([] !== $params) {
            $requestParams = array_merge($requestParams, $params);
        }

        return $this->client->search($requestParams)->asArray();
    }
}
