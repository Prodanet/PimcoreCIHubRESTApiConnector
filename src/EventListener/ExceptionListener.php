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

namespace CIHub\Bundle\SimpleRESTAdapterBundle\EventListener;

use CIHub\Bundle\SimpleRESTAdapterBundle\Exception\AccessDeniedException;
use CIHub\Bundle\SimpleRESTAdapterBundle\Exception\EndpointExceptionInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

final class ExceptionListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $exceptionEvent): void
    {
        $throwable = $exceptionEvent->getThrowable();
        if ($throwable instanceof EndpointExceptionInterface
            || str_starts_with($exceptionEvent->getRequest()->get('_route'), 'datahub_rest_')
            || str_starts_with($exceptionEvent->getRequest()->getPathInfo(), '/datahub/rest/')) {
            $jsonResponse = new JsonResponse([
                'status' => $throwable->getCode(),
                'message' => $throwable->getMessage(),
            ], 400);

            $exceptionEvent->setResponse($jsonResponse);
        }
    }
}
