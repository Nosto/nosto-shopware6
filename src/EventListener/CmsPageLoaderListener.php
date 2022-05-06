<?php declare(strict_types=1);

namespace Od\NostoIntegration\EventListener;

use Nosto\NostoException;
use Nosto\Request\Http\Exception\{AbstractHttpException, HttpResponseException};
use Od\NostoIntegration\Model\ConfigProvider;
use Od\NostoIntegration\Service\CategoryMerchandising\SessionLookupResolver;
use Shopware\Storefront\Framework\Routing\StorefrontResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\{Cookie, RequestStack};
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class CmsPageLoaderListener implements EventSubscriberInterface
{
    private RequestStack $requestStack;
    private SessionLookupResolver $sessionLookupResolver;
    private ConfigProvider $configProvider;

    public function __construct(
        RequestStack $requestStack,
        SessionLookupResolver $sessionLookupResolver,
        ConfigProvider $configProvider
    ) {
        $this->requestStack = $requestStack;
        $this->sessionLookupResolver = $sessionLookupResolver;
        $this->configProvider = $configProvider;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse'
        ];
    }

    /**
     * @throws HttpResponseException
     * @throws NostoException
     * @throws AbstractHttpException
     */
    public function onKernelResponse(ResponseEvent $event)
    {
        if (!($event->getResponse() instanceof StorefrontResponse)) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        $customerId = $this->sessionLookupResolver->getCustomerId();

        if (!$customerId) {
            return;
        }

        $response = $event->getResponse();
        $cookie = Cookie::create('2c_cId', $customerId);
        $cookie->setSecureDefault($request->isSecure());
        $response->headers->setCookie($cookie);

        $activeRoute = $request->attributes->get('_route');
        $enabledCache = $this->configProvider->isEnabledNotLoggedInCache();
        $customer = $request->attributes->get('sw-sales-channel-context')->getCustomer();
        $isCategoryRoute = $activeRoute === 'frontend.navigation.page';

        if ((!$customer && $isCategoryRoute && !$enabledCache) || ($customer && $isCategoryRoute)) {
            $response->headers->addCacheControlDirective('no-store');
        }
    }
}