<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Subscriber;

use Nosto\NostoIntegration\Decorator\Storefront\Framework\Cookie\NostoCookieProvider;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class NostoCookieSubscriber implements EventSubscriberInterface
{
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

        // TODO: Adjust the code.
        if (!$request->attributes->has('setNostoCookieDemo')) {
            return;
        }

        $cookieValue1 = $request->attributes->get('setNostoCookieDemo');

        $cookie = new Cookie(
            'nostoCookieFilter',
            $cookieValue1,
            strtotime('+1 day'),
            '/',
            $request->getHost(),
            $request->isSecure(),
            false,
            false,
            Cookie::SAMESITE_LAX
        );

        $response->headers->setCookie($cookie);

        if (!$request->attributes->has('setNostoCookie')) {
            return;
        }

        $cookieValue2 = $request->attributes->get('setNostoCookie');

        $cookie2 = new Cookie(
            'nostoCookieFilterMapping',
            $cookieValue2,
            strtotime('+1 day'),
            '/',
            $request->getHost(),
            $request->isSecure(),
            false,
            false,
            Cookie::SAMESITE_LAX
        );

        $response->headers->setCookie($cookie2);
    }
}
