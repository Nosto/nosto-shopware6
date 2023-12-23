<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\EventListener;

use Nosto\NostoIntegration\Service\CategoryMerchandising\NostoCacheResolver;
use Nosto\NostoIntegration\Service\CategoryMerchandising\SessionLookupResolver;
use Nosto\NostoIntegration\Utils\Logger\ContextHelper;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Routing\StorefrontResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\{Cookie, RequestStack};
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

class CmsPageLoaderListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly SessionLookupResolver $sessionLookupResolver,
        private readonly NostoCacheResolver $cacheResolver,
        private readonly LoggerInterface $logger,
    ) {
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
            /** @var SalesChannelContext $salesChannelContext */
            $salesChannelContext = $request->attributes->get('sw-sales-channel-context');

            $sessionId = $this->sessionLookupResolver->getSessionId(
                $salesChannelContext->getContext(),
                $salesChannelContext->getSalesChannel()->getId(),
                $salesChannelContext->getContext()->getLanguageId(),
            );
        } catch (Throwable $throwable) {
            $sessionId = null;
            $this->logger->error(
                sprintf(
                    'Unable to load resolve session, reason: %s',
                    $throwable->getMessage(),
                ),
                ContextHelper::createContextFromException($throwable),
            );
        }

        if (!$request->cookies->has('nosto-integration-track-allow')) {
            $cookie = Cookie::create('nosto-integration-track-allow', '1', strtotime('+30 days'))
                ->withHttpOnly(false);
            $cookie->setSecureDefault($request->isSecure());
            $response->headers->setCookie($cookie);
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
