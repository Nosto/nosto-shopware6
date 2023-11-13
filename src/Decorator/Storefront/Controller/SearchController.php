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
use Symfony\Component\Routing\Annotation\Route;

/**
 * @see ShopwareSearchController
 */
#[Route(
    defaults: [
        '_routeScope' => ['storefront'],
    ]
)]
class SearchController extends StorefrontController
{
    public function __construct(
        private readonly ShopwareSearchController $decorated,
        private readonly FilterHandler $filterHandler,
        private readonly ConfigProvider $configProvider,
        private readonly SearchService $searchService,
        private readonly SearchPageLoader $searchPageLoader,
        ContainerInterface $container
    ) {
        $this->container = $container;
    }

    #[Route(
        path: '/search',
        name: 'frontend.search.page',
        defaults: [
            '_httpCache' => true,
        ],
        methods: ['GET']
    )]
    public function search(SalesChannelContext $context, Request $request): Response
    {
        if (!$this->configProvider->isSearchEnabled($context->getSalesChannelId(), $context->getLanguageId())) {
            return $this->decorated->search($context, $request);
        }

        try {
            $page = $this->searchPageLoader->load($request, $context);
            if ($page->getListing()->getTotal() === 1) {
                $product = $page->getListing()->first();
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

        return $this->renderStorefront('@Storefront/storefront/page/search/index.html.twig', [
            'page' => $page,
        ]);
    }

    #[Route(
        path: '/suggest',
        name: 'frontend.search.suggest',
        defaults: [
            'XmlHttpRequest' => true,
            '_httpCache' => true,
        ],
        methods: ['GET']
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
        methods: ['GET', 'POST']
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
        methods: ['GET', 'POST']
    )]
    public function filter(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        if (!SearchHelper::shouldHandleRequest($salesChannelContext, $this->configProvider)) {
            return $this->decorated->filter($request, $salesChannelContext);
        }

        $criteria = new Criteria();
        $this->searchService->doFilter($request, $criteria, $salesChannelContext);

        if (!$criteria->hasExtension('nostoAvailableFilters')) {
            return $this->decorated->filter($request, $salesChannelContext);
        }

        return new JsonResponse($this->filterHandler->handleAvailableFilters($criteria));
    }
}
