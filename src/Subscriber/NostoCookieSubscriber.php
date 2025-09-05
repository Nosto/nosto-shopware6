<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Subscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class NostoCookieSubscriber implements EventSubscriberInterface
{
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
        $nostoCookieFilter = new Cookie(
            'nostoCookieFilter',
            $nostoApiResultValue,
            strtotime('+1 day'),
            '/',
            $request->getHost(),
            $request->isSecure(),
            false,
            false,
            Cookie::SAMESITE_LAX,
        );

        $response->headers->setCookie($nostoCookieFilter);

        if (!$request->attributes->has('setNostoCookie')) {
            return;
        }
        $nostoCookieValue = $request->attributes->get('setNostoCookie');
        $nostoCookieFilterMapping = new Cookie(
            'nostoCookieFilterMapping',
            $nostoCookieValue,
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
}
