<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Decorator\Storefront\Controller;

use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Search\Api\SearchService;
use Nosto\NostoIntegration\Search\Request\Handler\FilterHandler;
use Nosto\NostoIntegration\Struct\Redirect;
use Nosto\NostoIntegration\Utils\SearchHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\SearchController as ShopwareSearchController;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Page\Search\SearchPageLoadedHook;
use Shopware\Storefront\Page\Search\SearchPageLoader;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @see ShopwareSearchController
 */
#[Route(
    defaults: [
        '_routeScope' => ['storefront'],
    ],
)]
class SearchController extends StorefrontController
{
    public function __construct(
        private readonly ShopwareSearchController $decorated,
        private readonly FilterHandler $filterHandler,
        private readonly ConfigProvider $configProvider,
        private readonly SearchService $searchService,
        private readonly SearchPageLoader $searchPageLoader,
        ContainerInterface $container,
    ) {
        $this->container = $container;
    }

    #[Route(
        path: '/search',
        name: 'frontend.search.page',
        defaults: [
            '_httpCache' => true,
            '_routeScope' => ['storefront'],
        ],
        methods: ['GET'],
    )]
    public function search(SalesChannelContext $context, Request $request): Response
    {
        $isPreviewEnabled = $this->isPreviewEnabled($request);

        if (!$this->configProvider->isSearchEnabled(
            $context->getSalesChannelId(),
            $context->getLanguageId(),
        ) && !$isPreviewEnabled) {
            return $this->decorated->search($context, $request);
        }

        try {
            $page = $this->searchPageLoader->load($request, $context);
            if ($page->getListing()->getTotal() === 1) {
                $product = $page->getListing()->first();
                $redirectToPDP = $this->configProvider->isEnabledRedirectToThePDP(
                    $context->getSalesChannelId(),
                    $context->getLanguageId(),
                );
                if ($redirectToPDP) {
                    $productId = $product->getId();

                    return $this->forwardToRoute('frontend.detail.page', [], [
                        'productId' => $productId,
                    ]);
                }

                if ($request->get('search') === $product->getProductNumber()) {
                    $productId = $product->getId();

                    return $this->forwardToRoute('frontend.detail.page', [], [
                        'productId' => $productId,
                    ]);
                }
            }
        } catch (RoutingException $e) {
            if ($e->getErrorCode() !== RoutingException::MISSING_REQUEST_PARAMETER_CODE) {
                throw $e;
            }

            return $this->forwardToRoute('frontend.home.page');
        }

        /** @var Redirect $redirect */
        if ($redirect = $context->getContext()->getExtension('nostoRedirect')) {
            return $this->redirect($redirect->getLink(), 301);
        }

        $this->hook(new SearchPageLoadedHook($page, $context));

        $response = $this->renderStorefront('@Storefront/storefront/page/search/index.html.twig', [
            'page' => $page,
        ]);

        $isEnabledCache = $this->configProvider->isCacheEnabled(
            $context->getSalesChannelId(),
            $context->getLanguageId(),
        );
        if ($isEnabledCache) {
            $cacheTtl = $this->configProvider->getCacheTtl(
                $context->getSalesChannelId(),
                $context->getLanguageId(),
            );
            $cacheTtlSeconds = $cacheTtl * 60;
            $response->setPublic();
            $response->setMaxAge($cacheTtlSeconds);
            $response->setSharedMaxAge($cacheTtlSeconds);
            $response->setStaleWhileRevalidate($cacheTtlSeconds);
            $expiryTime = new \DateTimeImmutable(sprintf('+%d seconds', $cacheTtlSeconds));
            $response->setExpires($expiryTime);
        } else {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
        }
        return $response;
    }

    #[Route(
        path: '/suggest',
        name: 'frontend.search.suggest',
        defaults: [
            'XmlHttpRequest' => true,
            '_httpCache' => true,
        ],
        methods: ['GET'],
    )]
    public function suggest(SalesChannelContext $context, Request $request): Response
    {
        return $this->decorated->suggest($context, $request);
    }

    #[Route(
        path: '/widgets/search',
        name: 'widgets.search.pagelet.v2',
        defaults: [
            'XmlHttpRequest' => true,
            '_routeScope' => ['storefront'],
            '_httpCache' => true,
        ],
        methods: ['GET', 'POST'],
    )]
    public function ajax(Request $request, SalesChannelContext $context): Response
    {
        return $this->decorated->ajax($request, $context);
    }

    #[Route(
        path: '/widgets/search/filter',
        name: 'widgets.search.filter',
        defaults: [
            'XmlHttpRequest' => true,
            '_routeScope' => ['storefront'],
            '_httpCache' => true,
        ],
        methods: ['GET', 'POST'],
    )]
    public function filter(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        if (!SearchHelper::shouldHandleRequest($salesChannelContext, $this->configProvider, false, $request)) {
            return $this->decorated->filter($request, $salesChannelContext);
        }

        $criteria = new Criteria();
        $this->searchService->doFilter($request, $criteria, $salesChannelContext);

        if (!$criteria->hasExtension('nostoAvailableFilters')) {
            return $this->decorated->filter($request, $salesChannelContext);
        }

        return new JsonResponse(
            $this->filterHandler->handleAvailableFilters($criteria),
        );
    }

    #[Route(
        path: '/nosto/preview-toggle',
        name: 'frontend.nosto.preview.toggle',
        defaults: [
            'XmlHttpRequest' => true,
        ],
        methods: ['POST'],
    )]
    public function togglePreview(Request $request, SessionInterface $session): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Ako nije eksplicitno 'true', tretiraj kao false
        $isEnabled = isset($data['preview']) && $data['preview'] === true;

        // Upamti u sesiji da li je preview mod uključen
        $session->set('nosto_preview_enabled', $isEnabled);

        // (Opcionalno) dodeli u globalni Twig varijablu ako koristiš Twig negde
        // $this->twig->addGlobal('nostoPreviewEnabled', $isEnabled);

        return new JsonResponse([
            'success' => true,
            'preview' => $isEnabled,
            'session_value' => $session->get('nosto_preview_enabled'),
        ]);
    }

    private function isPreviewEnabled(Request $request): bool
    {
        // Check cookie first (automatically sent with every request)
        $cookieValue = $request->cookies->get('nosto_preview');
        if ($cookieValue !== null) {
            return $cookieValue === '1';
        }

        // Fallback to session
        if ($request->hasSession() && $request->getSession()->isStarted()) {
            return $request->getSession()->get('nosto_preview_enabled', false);
        }

        return false;
    }
}
