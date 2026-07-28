<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Subscriber;

use Nosto\NostoIntegration\Decorator\Storefront\Framework\Cookie\NostoCookieProvider;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Subscriber\FrontendSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class FrontendSubscriberLegacyCookieTest extends TestCase
{
    public function testLegacyEssentialCookieMigratesToNewKey(): void
    {
        $request = Request::create('/');
        $request->cookies->set(NostoCookieProvider::LEGACY_TRACK_ALLOW_COOKIE_KEY, '1');

        $response = $this->handle($request, ignoreCookieConsent: false);

        $migrated = $this->findCookie($response, NostoCookieProvider::NOSTO_COOKIE_KEY);
        self::assertNotNull($migrated, 'Expected the new cookie to be set when the legacy cookie is present.');
        self::assertSame('1', $migrated->getValue());
    }

    public function testNoConsentCookieDoesNotSetNewKey(): void
    {
        $request = Request::create('/');

        $response = $this->handle($request, ignoreCookieConsent: false);

        self::assertNull(
            $this->findCookie($response, NostoCookieProvider::NOSTO_COOKIE_KEY),
            'The new cookie must not be set without prior consent.',
        );
    }

    private function handle(Request $request, bool $ignoreCookieConsent): Response
    {
        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('isEnabledIgnoreCookieConsent')->willReturn($ignoreCookieConsent);

        $response = new Response();
        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        );

        (new FrontendSubscriber($configProvider))->onKernelResponse($event);

        return $response;
    }

    private function findCookie(Response $response, string $name): ?Cookie
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $name) {
                return $cookie;
            }
        }

        return null;
    }
}
