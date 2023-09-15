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

use Nelmio\ApiDocBundle\Render\RenderOpenApi;
use Pimcore\Controller\FrontendController;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Serializer;

#[Route("/pimcore-datahub-webservices/simplerest")]
class SwaggerController extends FrontendController
{
    /**
     * @return Response
     */
    #[Route("/swagger", name: "simple_rest_adapter_swagger_ui", methods: ['GET'])]
    public function userInterfaceAction(): Response
    {
        return $this->renderTemplate('@SimpleRESTAdapter/Swagger/index.html.twig', [
            'configUrl' => $this->generateUrl('simple_rest_adapter_swagger_config'),
        ]);
    }

    /**
     * @return Response
     */
    #[Route("/swagger-config", name: "simple_rest_adapter_swagger_config", methods: ["GET"])]
    public function configAction(Request $request, RenderOpenApi $renderOpenApi): Response
    {
        return new Response($renderOpenApi->renderFromRequest($request, RenderOpenApi::JSON, 'ci_hub'));
    }
}
