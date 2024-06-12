<?php

namespace CIHub\Bundle\SimpleRESTAdapterBundle\EventListener;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class CorsListener implements EventSubscriberInterface
{
    private LoggerInterface|NullLogger|null $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        if (null === $logger) {
            $logger = new NullLogger();
        }
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelRequest',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {

        if (HttpKernelInterface::MAIN_REQUEST !== $event->getRequestType()) {
            $this->logger->debug('Not a master type request, skipping CORS checks.');

            return;
        }
        if (!str_starts_with('datahub_rest_endpoints', $event->getRequest()->get('_route'))) {
            $this->logger->debug('Not a datahub request, skipping CORS checks.');

            return;
        }
        $crossOriginHeaders = [
            'Allow' => 'GET, OPTIONS',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => implode(',', [
                'Origin',
                'Accept',
                'DNT',
                'X-User-Token',
                'Keep-Alive',
                'User-Agent',
                'X-Requested-With',
                'If-Modified-Since',
                'Cache-Control',
                'Content-Type'
            ]),
        ];

        $request = $event->getRequest();
        if($request->headers->has('Origin')) {
            $crossOriginHeaders['Access-Control-Allow-Origin'] = $request->headers->get('Origin');
        }

        $event->getResponse()->headers->add($crossOriginHeaders);
    }
}
