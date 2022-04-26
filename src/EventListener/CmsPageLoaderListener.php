<?php declare(strict_types=1);

namespace Od\NostoIntegration\EventListener;

use Nosto\NostoException;
use Nosto\Operation\Session\NewSession;
use Nosto\Request\Http\Exception\{AbstractHttpException, HttpResponseException};
use Od\NostoIntegration\Model\Nosto\Account\Provider;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Storefront\Framework\Routing\StorefrontResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\{Cookie, RequestStack};
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class CmsPageLoaderListener implements EventSubscriberInterface
{
    private RequestStack $requestStack;
    private Provider $accountProvider;

    public function __construct(
        RequestStack $requestStack,
        Provider $accountProvider
    ) {
        $this->requestStack = $requestStack;
        $this->accountProvider = $accountProvider;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductEvents::PRODUCT_LISTING_CRITERIA => 'onProductListingCriteria',
            KernelEvents::RESPONSE => 'onKernelResponse'
        ];
    }

    /**
     * @throws NostoException
     * @throws HttpResponseException
     * @throws AbstractHttpException
     */
    public function onProductListingCriteria(ProductListingCriteriaEvent $event)
    {
        $request = $this->requestStack->getCurrentRequest();
        $customerId = $request->cookies->get('2c_cId');
        $account = $this->accountProvider->get($request->attributes->get('sw-sales-channel-id'));

        if ($account && !$customerId) {
            $session = new NewSession($account->getNostoAccount(), '', false);
            $customerId = $session->execute();
        }

        $event->getSalesChannelContext()->addExtension('customerId', new ArrayStruct([
            'id' => $customerId
        ]));
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
        $extension = $event->getResponse()->getContext()->getExtension('customerId');
        $customerId = $extension ? $extension->getVars()['id'] : null;

        if (!$customerId) {
            return;
        }
        $response = $event->getResponse();
        $cookie = Cookie::create('2c_cId', $customerId);
        $cookie->setSecureDefault($request->isSecure());
        $response->headers->setCookie($cookie);
        $response->headers->addCacheControlDirective('no-store');
    }
}