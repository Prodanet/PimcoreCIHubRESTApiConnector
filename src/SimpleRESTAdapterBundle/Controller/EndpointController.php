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
use CIHub\Bundle\SimpleRESTAdapterBundle\Exception\AssetNotFoundException;
use CIHub\Bundle\SimpleRESTAdapterBundle\Manager\IndexManager;
use CIHub\Bundle\SimpleRESTAdapterBundle\Provider\AssetProvider;
use CIHub\Bundle\SimpleRESTAdapterBundle\Reader\ConfigReader;
use Exception;
use ONGR\ElasticsearchDSL\Query\FullText\MatchQuery;
use Pimcore\Model\Asset;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EndpointController extends BaseEndpointController
{
    /**
     * @return Response
     * @throws Exception
     */
    public function downloadAssetAction(): Response
    {
        $crossOriginHeaders = [
            'Allow' => 'GET, OPTIONS',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'authorization',
        ];

        // Send empty response for OPTIONS requests
        if ($this->request->isMethod('OPTIONS')) {
            return new Response('', 204, $crossOriginHeaders);
        }

        $configuration = $this->getDataHubConfiguration();
        $reader = new ConfigReader($configuration->getConfiguration());

        // Check if request is authenticated properly
        $this->checkAuthentication($reader->getApiKey());

        $id = $this->request->get('id');

        // Check if required parameters are missing
        $this->checkRequiredParameters(['id' => $id]);

        $asset = Asset::getById($id);

        if (!$asset instanceof Asset) {
            throw new AssetNotFoundException(sprintf('Element with ID \'%s\' not found.', $id));
        }

        $thumbnail = $this->request->get('thumbnail');
        $defaultPreviewThumbnail = $this->getParameter('simple_rest_adapter.default_preview_thumbnail');

        if (!empty($thumbnail) && ($asset instanceof Asset\Image || $asset instanceof Asset\Document)) {
            if (AssetProvider::CIHUB_PREVIEW_THUMBNAIL === $thumbnail && 'ciHub' === $reader->getType()) {
                if ($asset instanceof Asset\Image) {
                    $assetFile = $asset->getThumbnail($defaultPreviewThumbnail);
                } else {
                    $assetFile = $asset->getImageThumbnail($defaultPreviewThumbnail);
                }
            } else if ($asset instanceof Asset\Image) {
                $assetFile = $asset->getThumbnail($thumbnail);
            } else {
                $assetFile = $asset->getImageThumbnail($thumbnail);
            }
        } else {
            $assetFile = $asset;
        }

        $response = new StreamedResponse();
        $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, basename($assetFile->getPath()));
        $response->headers->set('Content-Type', $assetFile->getMimetype());
        $response->headers->set('Content-Length', $assetFile->getFileSize());

        $stream = $assetFile->getStream();
        return $response->setCallback(function () use ($stream) {
            fpassthru($stream);
        });
    }

    /**
     * @param IndexManager      $indexManager
     * @param IndexQueryService $indexService
     *
     * @return JsonResponse
     */
    public function getElementAction(IndexManager $indexManager, IndexQueryService $indexService): JsonResponse
    {
        $configuration = $this->getDataHubConfiguration();
        $reader = new ConfigReader($configuration->getConfiguration());

        // Check if request is authenticated properly
        $this->checkAuthentication($reader->getApiKey());

        $id = $this->request->get('id');
        $type = $this->request->get('type');

        // Check if required parameters are missing
        $this->checkRequiredParameters(['id' => $id, 'type' => $type]);

        $indices = [];

        if ('asset' === $type && $reader->isAssetIndexingEnabled()) {
            $indices = [
                $indexManager->getIndexName(IndexManager::INDEX_ASSET, $this->config),
                $indexManager->getIndexName(IndexManager::INDEX_ASSET_FOLDER, $this->config),
            ];
        } elseif ('object' === $type && $reader->isObjectIndexingEnabled()) {
            $indices = array_merge(
                [$indexManager->getIndexName(IndexManager::INDEX_OBJECT_FOLDER, $this->config)],
                array_map(function ($className) use ($indexManager) {
                    return $indexManager->getIndexName(strtolower($className), $this->config);
                }, $reader->getObjectClassNames())
            );
        }

        foreach ($indices as $index) {
            try {
                $result = $indexService->get($id, $index);
            } catch (Exception $ignore) {
                $result = [];
            }

            if (isset($result['found']) && true === $result['found']) {
                break;
            }
        }

        if (empty($result) || false === $result['found']) {
            throw new AssetNotFoundException(sprintf('Element with type \'%s\' and ID \'%s\' not found.', $type, $id));
        }

        return $this->json($this->buildResponse($result, $reader));
    }

    /**
     * @param IndexManager            $indexManager
     * @param IndexQueryService       $indexService
     *
     * @return JsonResponse
     */
    public function searchAction(Request $request, IndexManager $indexManager, IndexQueryService $indexService): JsonResponse
    {
        $configuration = $this->getDataHubConfiguration();
        $reader = new ConfigReader($configuration->getConfiguration());
        // Check if request is authenticated properly
        $this->checkAuthentication($reader->getApiKey());
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
     * @param IndexManager $indexManager
     * @param IndexQueryService $indexService
     *
     * @return JsonResponse
     * @throws Exception
     */
    public function treeItemsAction(IndexManager $indexManager, IndexQueryService $indexService): JsonResponse
    {
        $configuration = $this->getDataHubConfiguration();
        $reader = new ConfigReader($configuration->getConfiguration());

        // Check if request is authenticated properly
        $this->checkAuthentication($reader->getApiKey());

        $type = $this->request->get('type');

        // Check if required parameters are missing
        $this->checkRequiredParameters(['type' => $type]);

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

        $search = $indexService->createSearch();
        $this->applySearchSettings($search);
        $this->applyQueriesAndAggregations($search, $reader);
        $search->addQuery(new MatchQuery('system.parentId', $parentId));

        $result = $indexService->search(implode(',', $indices), $search->toArray());

        return $this->json($this->buildResponse($result, $reader));
    }
}
