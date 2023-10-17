<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Storefront\Controller;

use Nosto\NostoIntegration\Storefront\Page\Search\SearchPageLoader;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Search\Request\Handler\FilterHandler;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\SearchController as ShopwareSearchController;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class SearchController extends StorefrontController
{
    public function __construct(
        private readonly ShopwareSearchController $decorated,
        private readonly FilterHandler $filterHandler,
        private readonly ConfigProvider $configProvider,
        private readonly ?SearchPageLoader $searchPageLoader,
        ContainerInterface $container
    ) {
        $this->container = $container;
    }

    #[Route(path: '/search', name: 'frontend.search.page', defaults: ['_httpCache' => true], methods: ['GET'])]
    public function search(SalesChannelContext $context, Request $request): Response
    {
        $page = $this->searchPageLoader->load($request, $context);

        // TODO: redirects/landingpages

        return $this->renderStorefront('@Storefront/storefront/page/search/index.html.twig', ['page' => $page]);
    }

    #[Route(path: '/suggest', name: 'frontend.search.suggest', defaults: ['XmlHttpRequest' => true, '_httpCache' => true], methods: ['GET'])]
    public function suggest(SalesChannelContext $context, Request $request): Response
    {
        return $this->decorated->suggest($context, $request);
    }

    #[Route(path: '/widgets/search', name: 'widgets.search.pagelet.v2', defaults: ['XmlHttpRequest' => true, '_routeScope' => ['storefront'], '_httpCache' => true], methods: ['GET', 'POST'])]
    public function ajax(Request $request, SalesChannelContext $context): Response
    {
        return $this->decorated->ajax($request, $context);
    }

    #[Route(path: '/widgets/search/filter', name: 'widgets.search.filter', defaults: ['XmlHttpRequest' => true, '_routeScope' => ['storefront'], '_httpCache' => true], methods: ['GET', 'POST'])]
    public function filter(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        if (!$this->configProvider->isSearchEnabled()) {
            return $this->decorated->filter($request, $salesChannelContext);
        }

        $event = new ProductSearchCriteriaEvent($request, new Criteria(), $salesChannelContext);
        $this->findologicSearchService->doFilter($event);

        $result = $this->filterHandler->handleAvailableFilters($event);
        if (!$event->getCriteria()->hasExtension('flAvailableFilters')) {
            return $this->decorated->filter($request, $salesChannelContext);
        }

        return new JsonResponse($result);
    }
}
