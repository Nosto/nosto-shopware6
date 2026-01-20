<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Subscriber;

use Nosto\NostoIntegration\Decorator\Storefront\Framework\Cookie\NostoCookieProvider;
use Nosto\NostoIntegration\Service\FilterPayloadService;
use Nosto\NostoIntegration\Service\FilterPayloadStore;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class NostoCookieSubscriber implements EventSubscriberInterface
{
    private const TOKEN_PREFIX = 't:';

    private const TOKEN_TTL_SECONDS = 86400;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly FilterPayloadStore $filterPayloadStore,
        private readonly FilterPayloadService $filterPayloadService,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();
        $this->createAbTestsCookie($request, $response);

        if (!$request->attributes->has('nostoAPIResult')) {
            return;
        }

        $nostoApiResultValue = $request->attributes->get('nostoAPIResult');
        $encodedPayload = $this->encodePayload($nostoApiResultValue);
        $existingFilterPayload = $this->filterPayloadService->resolveCookiePayload(
            $request->cookies->get(NostoCookieProvider::NOSTO_FILTERS_KEY),
        );
        if ($existingFilterPayload !== $encodedPayload) {
            $cookieValue = $this->wrapToken(
                $this->filterPayloadStore->store(
                    $encodedPayload,
                    self::TOKEN_TTL_SECONDS,
                ),
                $encodedPayload,
            );
            $nostoCookieFilter = new Cookie(
                NostoCookieProvider::NOSTO_FILTERS_KEY,
                $cookieValue,
                strtotime('+1 day'),
                '/',
                $request->getHost(),
                $request->isSecure(),
                false,
                false,
                Cookie::SAMESITE_LAX,
            );

            $response->headers->setCookie($nostoCookieFilter);
        }

        if (!$request->attributes->has('setNostoCookie')) {
            return;
        }
        $nostoCookieValue = $request->attributes->get('setNostoCookie');
        $existingMappingPayload = $this->filterPayloadService->resolveCookiePayload(
            $request->cookies->get(NostoCookieProvider::NOSTO_FILTERS_MAPPING_KEY),
        );
        if ($existingMappingPayload === $nostoCookieValue) {
            return;
        }
        $nostoCookieFilterMapping = new Cookie(
            NostoCookieProvider::NOSTO_FILTERS_MAPPING_KEY,
            $this->wrapToken(
                $this->filterPayloadStore->store((string) $nostoCookieValue, self::TOKEN_TTL_SECONDS),
                (string) $nostoCookieValue,
            ),
            strtotime('+1 day'),
            '/',
            $request->getHost(),
            $request->isSecure(),
            false,
            false,
            Cookie::SAMESITE_LAX,
        );

        $response->headers->setCookie($nostoCookieFilterMapping);
    }

    public function createAbTestsCookie(Request $request, Response $response): void
    {
        if (!$request->attributes->has('setNostoAbTestsCookie') || str_contains($request->getRequestUri(), "filter")) {
            return;
        }

        $nostoCookieValue = $request->attributes->get('setNostoAbTestsCookie');
        $nostoAbCookie = new Cookie(
            'nosto_ab_tests',
            $nostoCookieValue,
            strtotime('+1 day'),
            '/',
            null,
            $request->isSecure(),
            false,
            false,
            Cookie::SAMESITE_LAX,
        );

        $response->headers->setCookie($nostoAbCookie);
    }

    private function encodePayload(string $payload): string
    {
        if (function_exists('zstd_compress')) {
            $compressed = zstd_compress($payload, 11);
            if ($compressed !== false) {
                return base64_encode($compressed);
            }
        }

        if (function_exists('gzcompress')) {
            $compressed = gzcompress($payload, 9);
            if ($compressed !== false) {
                return base64_encode($compressed);
            }
        }

        return $payload;
    }

    private function wrapToken(?string $token, string $fallback): string
    {
        if ($token) {
            return self::TOKEN_PREFIX . $token;
        }

        return $fallback;
    }
}
