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

use CIHub\Bundle\SimpleRESTAdapterBundle\Traits\TagsFilterTrait;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Attributes as OA;
use Pimcore\Model\Element\Tag;
use Pimcore\Model\Element\Tag\Listing;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: ['/datahub/rest/{config}', '/pimcore-datahub-webservices/simplerest/{config}'], name: 'datahub_rest_endpoints_filter')]
#[Security(name: 'Bearer')]
#[OA\Tag(name: 'Search')]
final class FilterController extends BaseEndpointController
{
    use TagsFilterTrait;

    #[Route(path: '/filter', name: 'get', methods: ['GET'])]
    public function get(): JsonResponse
    {
        $listing = new Listing();
        $listing
            ->setCondition('ISNULL(parentId) OR parentId = 0')
            ->setOrderKey('id')
            ->setOrder('ASC');
        $tagsList = $listing->getTags();
        $tagRoot = array_filter($tagsList, static fn (Tag $tag): bool => 0 === $tag->getParentId());
        $tagRoot = array_map(fn (Tag $tag): array => $this->convertTagToArray($tag), $tagRoot);
        $tagRoot = array_filter($tagRoot, static fn (array $item): bool => isset($item['items']));

        return new JsonResponse($this->mergeTopLevelItems($tagRoot));
    }
}
