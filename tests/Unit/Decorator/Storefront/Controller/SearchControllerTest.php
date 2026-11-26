<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Decorator\Storefront\Controller;

use Nosto\NostoIntegration\Decorator\Storefront\Controller\SearchController;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Search\Api\SearchService;
use Nosto\NostoIntegration\Search\Request\Handler\FilterHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\SearchController as ShopwareSearchController;
use Shopware\Storefront\Page\Search\SearchPage;
use Shopware\Storefront\Page\Search\SearchPageLoader;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class SearchControllerTest extends TestCase
{
    #[DataProvider('redirectSettings')]
    public function testEmptyListingWithOneReportedHitRendersSearch(int $redirectToPdp): void
    {
        $context = Context::createDefaultContext();
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn($context);

        // Exercise the controller guard independently of the total correction upstream.
        $page = new SearchPage();
        $page->setListing(new ProductListingResult(
            'product',
            1,
            new ProductCollection(),
            null,
            new Criteria(),
            $context,
        ));
        $pageLoader = $this->createMock(SearchPageLoader::class);
        $pageLoader->method('load')->willReturn($page);

        $config = $this->createMock(ConfigProvider::class);
        $config->method('isSearchEnabled')->willReturn(true);
        $config->method('isEnabledRedirectToThePDP')->willReturn($redirectToPdp);

        $controller = $this->getMockBuilder(SearchController::class)
            ->setConstructorArgs([
                $this->createMock(ShopwareSearchController::class),
                $this->createMock(FilterHandler::class),
                $config,
                $this->createMock(SearchService::class),
                $pageLoader,
                new Container(),
            ])
            ->onlyMethods(['forwardToRoute', 'hook', 'renderStorefront'])
            ->getMock();
        $controller->expects(self::never())->method('forwardToRoute');
        $response = new Response();
        $controller->expects(self::once())->method('renderStorefront')
            ->with('@Storefront/storefront/page/search/index.html.twig', [
                'page' => $page,
            ])
            ->willReturn($response);

        self::assertSame($response, $controller->search(
            $salesChannelContext,
            Request::create('/search?search=missing-product'),
        ));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function redirectSettings(): iterable
    {
        yield 'PDP redirect enabled' => [1];
        yield 'PDP redirect disabled' => [0];
    }
}
