<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Decorator\Storefront\Controller;

use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Search\Api\SearchService;
use Nosto\NostoIntegration\Search\Request\Handler\FilterHandler;
use Nosto\NostoIntegration\Utils\SearchHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\CmsController as ShopwareCmsController;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(
    defaults: [
        '_routeScope' => ['storefront'],
    ]
)]
class CmsController extends StorefrontController
{
    public function __construct(
        private readonly ShopwareCmsController $decorated,
        private readonly FilterHandler $filterHandler,
        private readonly SearchService $searchService,
        private readonly ConfigProvider $configProvider,
    ) {
    }

    #[Route(
        path: '/widgets/cms/{id}',
        name: 'frontend.cms.page',
        defaults: [
            'id' => null,
            'XmlHttpRequest' => true,
            '_httpCache' => true,
        ],
        methods: ['GET', 'POST']
    )]
    public function page(?string $id, Request $request, SalesChannelContext $salesChannelContext): Response
    {
        return $this->decorated->page($id, $request, $salesChannelContext);
    }

    #[Route(
        path: '/widgets/cms/navigation/{navigationId}',
        name: 'frontend.cms.navigation.page',
        defaults: [
            'navigationId' => null,
            'XmlHttpRequest' => true,
        ],
        methods: ['GET', 'POST']
    )]
    public function category(
        ?string $navigationId,
        Request $request,
        SalesChannelContext $salesChannelContext
    ): Response {
        return $this->decorated->category($navigationId, $request, $salesChannelContext);
    }

    #[Route(
        path: '/widgets/cms/navigation/{navigationId}/filter',
        name: 'frontend.cms.navigation.filter',
        defaults: [
            'XmlHttpRequest' => true,
            '_routeScope' => ['storefront'],
            '_httpCache' => true,
        ],
        methods: ['GET', 'POST']
    )]
    public function filter(string $navigationId, Request $request, SalesChannelContext $salesChannelContext): Response
    {
        if (!SearchHelper::shouldHandleRequest($salesChannelContext, $this->configProvider, true)) {
            return $this->decorated->filter($navigationId, $request, $salesChannelContext);
        }

        $criteria = new Criteria();
        $this->searchService->doFilter($request, $criteria, $salesChannelContext);

        if (!$criteria->hasExtension('nostoAvailableFilters')) {
            return $this->decorated->filter($navigationId, $request, $salesChannelContext);
        }

        return new JsonResponse($this->filterHandler->handleAvailableFilters($criteria));
    }

    #[Route(
        path: '/widgets/cms/buybox/{productId}/switch',
        name: 'frontend.cms.buybox.switch',
        defaults: [
            'productId' => null,
            'XmlHttpRequest' => true,
            '_routeScope' => ['storefront'],
            '_httpCache' => true,
        ],
        methods: ['GET']
    )]
    public function switchBuyBoxVariant(string $productId, Request $request, SalesChannelContext $context): Response
    {
        return $this->decorated->switchBuyBoxVariant($productId, $request, $context);
    }
}
