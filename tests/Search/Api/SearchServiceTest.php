<?php

namespace Nosto\NostoIntegration\Tests\Search\Api;

use Nosto\NostoIntegration\Search\Api\SearchService;
use Nosto\NostoIntegration\Search\Request\Handler\NavigationRequestHandler;
use Nosto\NostoIntegration\Search\Request\Handler\SearchRequestHandler;
use Nosto\NostoIntegration\Tests\TestCase;
use Nosto\NostoIntegration\Tests\Traits\DataHelpers\SalesChannelHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class SearchServiceTest extends TestCase
{
    use SalesChannelHelper;

    private SalesChannelContext $salesChannelContext;

    public function setUp(): void
    {
        parent::setUp();

        $this->salesChannelContext = $this->buildAndCreateSalesChannelContext();
    }

    public function testSearchUsesCorrectHandler(): void
    {
        $searchService = $this->getMockBuilder(SearchService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['allowRequest', 'handleRequest', 'buildSearchRequestHandler'])
            ->getMock();

        $searchService->expects($this->once())
            ->method('allowRequest')
            ->willReturn(true);
        $searchService->expects($this->once())
            ->method('buildSearchRequestHandler');

        $searchService->doSearch(new Request(), new Criteria(), $this->salesChannelContext);
    }

    public function testNavigationUsesCorrectHandler(): void
    {
        $searchService = $this->getMockBuilder(SearchService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['allowRequest', 'handleRequest', 'buildNavigationRequestHandler'])
            ->getMock();

        $searchService->expects($this->once())
            ->method('allowRequest')
            ->willReturn(true);
        $searchService->expects($this->once())
            ->method('buildNavigationRequestHandler');

        $searchService->doNavigation(new Request(), new Criteria(), $this->salesChannelContext);
    }

    public function requestProvider(): array
    {
        return [
            'search page' => [
                'request' => Request::create('http://localhost/de/search'),
                'expectedHandlerFunction' => 'buildSearchRequestHandler',
            ],
            'navigation page' => [
                'request' => new Request([], [], ['navigationId' => 'category-id']),
                'expectedHandlerFunction' => 'buildNavigationRequestHandler',
            ],
        ];
    }

    /**
     * @dataProvider requestProvider
     */
    public function testFilterUsesCorrectHandler(Request $request, ?string $expectedHandlerFunction): void
    {
        $searchService = $this->getMockBuilder(SearchService::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'allowRequest',
                'fetchFilters',
                'fetchSelectableFilters',
                $expectedHandlerFunction
            ])
            ->getMock();

        $searchService->expects($this->once())
            ->method('allowRequest')
            ->willReturn(true);

        $searchService->expects($this->once())
            ->method($expectedHandlerFunction);
        $searchService->expects($this->once())
            ->method('fetchFilters');
        $searchService->expects($this->once())
            ->method('fetchSelectableFilters');

        $searchService->doFilter($request, new Criteria(), $this->salesChannelContext);
    }

    public function testNostoIsDisabledOnNonRelevantPages()
    {
        $searchService = $this->getMockBuilder(SearchService::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'allowRequest',
                'disableNostoService',
                'fetchFilters',
                'fetchSelectableFilters',
            ])
            ->getMock();

        $searchService->expects($this->once())
            ->method('allowRequest')
            ->willReturn(true);

        $searchService->expects($this->once())
            ->method('disableNostoService');
        $searchService->expects($this->never())
            ->method('fetchFilters');
        $searchService->expects($this->never())
            ->method('fetchSelectableFilters');

        $searchService->doFilter(
            Request::create('http://localhost/de/imprint'),
            new Criteria(),
            $this->salesChannelContext
        );
    }
}
