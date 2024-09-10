<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Subscriber;

use Nosto\NostoIntegration\Decorator\Storefront\Framework\Cookie\NostoCookieProvider;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Struct\Config;
use Shopware\Storefront\Framework\Routing\StorefrontResponse;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class FrontendSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ConfigProvider $configProvider,
    ) {
    }

    /**
     * @return string[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            HeaderPageletLoadedEvent::class => 'onHeaderLoaded',
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onHeaderLoaded(HeaderPageletLoadedEvent $event): void
    {
        $config = $this->configProvider->toArray(
            $event->getSalesChannelContext()->getSalesChannelId(),
            $event->getSalesChannelContext()->getLanguageId(),
        );
        $nostoConfig = new Config($config);
        $event->getContext()->addExtension('nostoConfig', $nostoConfig);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!($event->getResponse() instanceof StorefrontResponse)) {
            return;
        }

        $response = $event->getResponse();
        $request = $event->getRequest();

        $this->migrateOverdoseCookie($response, $request);
        $this->triggerCookieBanner($response, $request);
    }

    private function migrateOverdoseCookie(Response $response, Request $request): void
    {
        if (
            $request->cookies->has(NostoCookieProvider::LEGACY_COOKIE_KEY) ||
            $this->configProvider->isEnabledIgnoreCookieConsent()
        ) {
            $cookie = Cookie::create(NostoCookieProvider::LEGACY_COOKIE_KEY, '1', strtotime('-1 day'))
                ->withHttpOnly(false);
            $cookie->setSecureDefault($request->isSecure());
            $response->headers->setCookie($cookie);

            $cookie = Cookie::create(NostoCookieProvider::NOSTO_COOKIE_KEY, '1', strtotime('+30 days'))
                ->withHttpOnly(false);
            $cookie->setSecureDefault($request->isSecure());
            $response->headers->setCookie($cookie);
        }
    }

    private function triggerCookieBanner(Response $response, Request $request): void
    {
        if (
            $request->cookies->getBoolean(NostoCookieProvider::SHOPWARE_COOKIE_PREFERENCE_KEY) &&
            !$request->cookies->has(NostoCookieProvider::NOSTO_COOKIE_KEY)
        ) {
            $cookie = Cookie::create(NostoCookieProvider::SHOPWARE_COOKIE_PREFERENCE_KEY, '1', strtotime('-1 day'))
                ->withHttpOnly(false);
            $cookie->setSecureDefault($request->isSecure());
            $response->headers->setCookie($cookie);
        }
    }
}
