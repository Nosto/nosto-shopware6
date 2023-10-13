<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\EventListener;

use Nosto\NostoIntegration\Service\CategoryMerchandising\NostoCacheResolver;
use Nosto\NostoIntegration\Service\CategoryMerchandising\SessionLookupResolver;
use Nosto\NostoIntegration\Utils\Logger\ContextHelper;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Storefront\Framework\Routing\StorefrontResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\{Cookie, RequestStack};
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

class CmsPageLoaderListener implements EventSubscriberInterface
{
    private RequestStack $requestStack;

    private SessionLookupResolver $sessionLookupResolver;

    private NostoCacheResolver $cacheResolver;

    private LoggerInterface $logger;

    public function __construct(
        RequestStack $requestStack,
        SessionLookupResolver $sessionLookupResolver,
        NostoCacheResolver $cacheResolver,
        LoggerInterface $logger
    ) {
        $this->requestStack = $requestStack;
        $this->sessionLookupResolver = $sessionLookupResolver;
        $this->cacheResolver = $cacheResolver;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!($event->getResponse() instanceof StorefrontResponse)) {
            return;
        }

        $response = $event->getResponse();
        $request = $this->requestStack->getCurrentRequest();

        if (!$this->cacheResolver->isCachingAllowed()) {
            $response->headers->addCacheControlDirective('no-store');
            $response->headers->addCacheControlDirective('private');
        }

        try {
            $sessionId = $this->sessionLookupResolver->getSessionId(new Context(new SystemSource()));
        } catch (Throwable $throwable) {
            $sessionId = null;
            $this->logger->error(
                sprintf(
                    'Unable to load resolve session, reason: %s',
                    $throwable->getMessage()
                ),
                ContextHelper::createContextFromException($throwable)
            );
        }

        $isNeedCreateSessionCookie = $sessionId !== null
            && $request->cookies->has('nosto-integration-track-allow')
            && !$request->cookies->has(SessionLookupResolver::NOSTO_SESSION_COOKIE);

        if ($isNeedCreateSessionCookie) {
            $cookie = Cookie::create(SessionLookupResolver::NOSTO_SESSION_COOKIE, $sessionId);
            $cookie->setSecureDefault($request->isSecure());
            $response->headers->setCookie($cookie);
        }
    }
}
