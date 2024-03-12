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
use CIHub\Bundle\SimpleRESTAdapterBundle\Manager\IndexManager;
use CIHub\Bundle\SimpleRESTAdapterBundle\Provider\AssetProvider;
use CIHub\Bundle\SimpleRESTAdapterBundle\Reader\ConfigReader;
use CIHub\Bundle\SimpleRESTAdapterBundle\Traits\RestHelperTrait;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use League\Flysystem\FilesystemException;
use Nelmio\ApiDocBundle\Annotation\Security;
use ONGR\ElasticsearchDSL\Query\FullText\MatchQuery;
use OpenApi\Attributes as OA;
use Pimcore\Bundle\AdminBundle\Service\ThumbnailService;
use Pimcore\Model\Asset\Image;
use Pimcore\Model\Asset\Image\Thumbnail;
use Pimcore\Model\Asset\Thumbnail\ThumbnailInterface;
use Pimcore\Model\Version;
use Pimcore\Tool\Storage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

#[Route(path: ['/datahub/rest/{config}/asset', '/pimcore-datahub-webservices/simplerest/{config}'], name: 'datahub_rest_endpoints_asset_')]
#[Security(name: 'Bearer')]
#[OA\Tag(name: 'Asset')]
class DownloadController extends BaseEndpointController
{
    use RestHelperTrait;

    /**
     * @throws FilesystemException
     */
    #[Route('/download', name: 'download', methods: ['GET', 'OPTIONS'])]
    #[OA\Get(
        description: 'Method to download binary file by asset ID.',
        summary: 'Download Asset',
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
                name: 'id',
                description: 'ID of the element.',
                in: 'query',
                required: true,
                schema: new OA\Schema(
                    type: 'integer'
                )
            ),
            new OA\Parameter(
                name: 'version',
                description: 'Version of the element.',
                in: 'query',
                required: false,
                schema: new OA\Schema(
                    type: 'integer'
                )
            ),
            new OA\Parameter(
                name: 'type',
                description: 'Type of elements – asset, object or version.',
                in: 'query',
                required: false,
                schema: new OA\Schema(
                    type: 'string',
                    enum: ['asset', 'object', 'version']
                )
            ),
            new OA\Parameter(
                name: 'thumbnail',
                description: 'Thumbnail config name',
                in: 'query',
                required: true,
                schema: new OA\Schema(
                    type: 'string'
                ),
                examples: [
                    new OA\Examples('pimcore-system-treepreview', '', value: 'pimcore-system-treepreview'),
                    new OA\Examples('galleryThumbnail', '', value: 'galleryThumbnail')
                ]
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation.',
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
    public function download(): Response
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

        // Check if request is authenticated properly
        $this->authManager->checkAuthentication();
        $configuration = $this->getDataHubConfiguration();
        $configReader = new ConfigReader($configuration->getConfiguration());

        $id = $this->request->query->getInt('id');

        // Check if required parameters are missing
        $this->checkRequiredParameters(['id' => $id]);
        $element = $this->getElementByIdType();
        if ($element instanceof Version) {
            $element = $element->getData();
        }

        if (!$element->isAllowed('view', $this->user)) {
            throw new AccessDeniedHttpException('Your request to create a folder has been blocked due to missing permissions');
        }

        $thumbnail = (string) $this->request->get('thumbnail');
        $elementFile = $element;
        if (!empty($thumbnail) && $element instanceof Image) {
            if (AssetProvider::CIHUB_PREVIEW_THUMBNAIL === $thumbnail && 'ciHub' === $configReader->getType()) {
                $defaultPreviewThumbnail = $this->getParameter('pimcore_ci_hub_adapter.default_preview_thumbnail');
                $elementFile = $element->getThumbnail($defaultPreviewThumbnail);
            } elseif (Thumbnail\Config::getByAutoDetect($thumbnail)) {
                $elementFile = $element->getThumbnail($thumbnail);
            }

            $storagePath = $this->getStoragePath($elementFile,
                $element->getId(),
                $element->getFilename(),
                $element->getRealPath(),
                $element->getChecksum()
            );

            $storage = Storage::get('thumbnail');
            if (!$storage->fileExists($storagePath)) {
                $response = new StreamedResponse(function () use ($elementFile) {
                    fpassthru($elementFile->getStream());
                }, 200, [
                    'Content-Type' => $elementFile->getMimetype(),
                ]);
            } else {
                $response = new StreamedResponse(function () use ($storagePath) {
                    $storage = Storage::get('thumbnail');
                    fpassthru($storage->readStream($storagePath));
                }, 200, [
                    'Content-Type' => $storage->mimeType($storagePath),
                ]);
            }
        }

        $response->headers->add($crossOriginHeaders);

        // If it is not a thumbnail then send DISPOSITION_ATTACHMENT of the download.
        if (!$this->request->request->has('thumbnail')) {
            $filename = basename(rawurldecode($elementFile->getPath()));
            $filenameFallback = preg_replace("/[^\w\-\.]/", '', $filename);
            $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename, $filenameFallback);
            $response->headers->set('Content-Length', $elementFile->getFileSize());
        }
        try {
            // Add cache to headers
            $this->addThumbnailCacheHeaders($response);
        } catch (\Exception $ignored) {
        }

        return $response;
    }

    /**
     * @throws ClientResponseException
     * @throws ServerResponseException
     */
    #[Route('/download-links', name: 'download_links', methods: ['GET'])]
    #[OA\Get(
        description: 'Method to return filtered list of links to assets.',
        summary: 'List assets',
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
                name: 'plu',
                description: 'Value from the "metaData.Default.PLU"',
                in: 'query',
                required: true,
                schema: new OA\Schema(
                    type: 'string'
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
                            description: 'Total count',
                            type: 'integer',
                            example: 1
                        ),
                        new OA\Property(
                            property: 'items',
                            description: 'Asset path',
                            type: 'array',
                            items: new OA\Items(
                                type: 'string',
                                example: '/datahub/rest/{config}/asset/download?id=1'
                            )
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Bad request data'
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
    public function downloadLinks(
        IndexManager $indexManager,
        IndexQueryService $indexService,
        Request $request,
        RouterInterface $router
    ): Response {
        $this->authManager->checkAuthentication();

        $configName = $this->config;
        $configuration = $this->getDataHubConfiguration();
        $configReader = new ConfigReader($configuration->getConfiguration());

        $plu = $request->query->getString('plu');

        $this->checkRequiredParameters(['plu' => $plu]);

        $indices = [];
        if ($configReader->isAssetIndexingEnabled()) {
            $indices = [$indexManager->getIndexName(IndexManager::INDEX_ASSET, $configName)];
        }

        $search = $indexService->createSearch();
        $this->applySearchSettings($search);

        $search->addQuery(new MatchQuery('metaData.Default.PLU', $plu));

        $result = $indexService->search(implode(',', $indices), $search->toArray());

        $hits = $result['hits'] ?? [];
        $total = $hits['total'] ?? 0;
        $entries = $hits['hits'] ?? [];

        $items = [];
        if ($total > 0) {
            $ids = array_map(function ($v) {
                return $v['_id'];
            }, $entries);

            $items = array_map(function ($id) use ($router, $configName) {
                return $router->generate('datahub_rest_endpoints_asset_download', [
                    'config' => $configName,
                    'id' => $id,
                ]);
            }, $ids);
        }

        return $this->json([
            'total_count' => $total,
            'items' => $items,
        ]);
    }

    public function getStoragePath(ThumbnailInterface $thumb, int $id, string $filename, string $realPlace, string $checksum): string
    {
        $thumbnail = $thumb->getConfig();
        $format = mb_strtolower($thumbnail->getFormat());
        $fileExt = pathinfo($filename, \PATHINFO_EXTENSION);

        // simple detection for source type if SOURCE is selected
        if ('source' == $format || empty($format)) {
            $thumbnail->setFormat('jpeg'); // default format for documents is JPEG not PNG (=too big)
            $optimizedFormat = true;
            $format = ThumbnailService::getAllowedFormat($fileExt, ['pjpeg', 'jpeg', 'gif', 'png'], 'png');
            if ('jpeg' === $format) {
                $format = 'pjpeg';
            }
        }

        $thumbDir = rtrim($realPlace, '/').'/'.$id.'/image-thumb__'.$id.'__'.$thumbnail->getName();
        $filename = preg_replace("/\.".preg_quote(pathinfo($filename, \PATHINFO_EXTENSION), '/').'$/i', '', $filename);

        // add custom suffix if available
        if ($thumbnail->getFilenameSuffix()) {
            $filename .= '~-~'.$thumbnail->getFilenameSuffix();
        }
        // add high-resolution modifier suffix to the filename
        if ($thumbnail->getHighResolution() > 1) {
            $filename .= '@'.$thumbnail->getHighResolution().'x';
        }

        $fileExtension = $format;
        if ('original' == $format) {
            $fileExtension = $fileExt;
        } elseif ('pjpeg' === $format || 'jpeg' === $format) {
            $fileExtension = 'jpg';
        }

        $filename .= '.'.$thumbnail->getHash([$checksum]).'.'.$fileExtension;

        return $thumbDir.'/'.$filename;
    }
}
