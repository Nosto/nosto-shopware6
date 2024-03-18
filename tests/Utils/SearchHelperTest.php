<?php

namespace Nosto\NostoIntegration\Tests\Utils;

use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Struct\Config;
use Nosto\NostoIntegration\Struct\NostoService;
use Nosto\NostoIntegration\Struct\PageInformation;
use Nosto\NostoIntegration\Tests\TestCase;
use Nosto\NostoIntegration\Tests\Traits\DataHelpers\ConfigHelper;
use Nosto\NostoIntegration\Tests\Traits\DataHelpers\SalesChannelHelper;
use Nosto\NostoIntegration\Utils\SearchHelper;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class SearchHelperTest extends TestCase
{
    use ConfigHelper;
    use SalesChannelHelper;

    private SalesChannelContext $salesChannelContext;

    public function nostoActiveProvider(): array
    {
        return [
            'Empty search key' => [
                'overrides' => [
                    'searchToken' => '',
                ],
                'isNavigation' => false,
                'expectedActive' => false,
            ],
            'Empty account ID' => [
                'overrides' => [
                    'accountID' => '',
                ],
                'isNavigation' => false,
                'expectedActive' => false,
            ],
            'Disabled search and navigation on search page' => [
                'overrides' => [
                    'enableSearch' => false,
                    'enableNavigation' => false,
                ],
                'isNavigation' => false,
                'expectedActive' => false,
            ],
            'Disabled search and navigation on category page' => [
                'overrides' => [
                    'enableSearch' => false,
                    'enableNavigation' => false,
                ],
                'isNavigation' => true,
                'expectedActive' => false,
            ],
            'Enabled search on search page' => [
                'overrides' => [
                    'enableSearch' => true,
                    'enableNavigation' => false,
                ],
                'isNavigation' => false,
                'expectedActive' => true,
            ],
            'Enabled navigation on category page' => [
                'overrides' => [
                    'enableSearch' => false,
                    'enableNavigation' => true,
                ],
                'isNavigation' => true,
                'expectedActive' => true,
            ],
            'Enabled search on category page' => [
                'overrides' => [
                    'enableSearch' => true,
                    'enableNavigation' => false,
                ],
                'isNavigation' => true,
                'expectedActive' => false,
            ],
            'Enabled navigation on search page' => [
                'overrides' => [
                    'enableSearch' => false,
                    'enableNavigation' => true,
                ],
                'isNavigation' => false,
                'expectedActive' => false,
            ],
        ];
    }

    /**
     * @dataProvider nostoActiveProvider
     */
    public function testNostoActive(array $overrides, bool $isNavigation, bool $expectedActive): void
    {
        $salesChannelContext = $this->buildAndCreateSalesChannelContext();
        $configProvider = new ConfigProvider(
            $this->getDefaultNostoConfigServiceMock($overrides),
        );

        $shouldHandleRequest = SearchHelper::shouldHandleRequest(
            $salesChannelContext,
            $configProvider,
            $isNavigation,
        );

        /** @var NostoService $nostoService */
        $nostoService = $salesChannelContext->getContext()->getExtension('nostoService');

        $this->assertSame($expectedActive, $shouldHandleRequest);
        $this->assertSame($expectedActive, $nostoService->getEnabled());
    }

    public function testExtensionsAreAdded(): void
    {
        $salesChannelContext = $this->buildAndCreateSalesChannelContext();
        $configProvider = new ConfigProvider(
            $this->getDefaultNostoConfigServiceMock(),
        );

        SearchHelper::shouldHandleRequest(
            $salesChannelContext,
            $configProvider,
        );

        /** @var Config $nostoConfig */
        $nostoConfig = $salesChannelContext->getContext()->getExtension('nostoConfig');
        /** @var PageInformation $nostoPageInformation */
        $nostoPageInformation = $salesChannelContext->getContext()->getExtension('nostoPageInformation');

        $this->assertInstanceOf(Config::class, $nostoConfig);
        $this->assertInstanceOf(PageInformation::class, $nostoPageInformation);
    }

    public function testIsSearchPage()
    {
        $randomRequest = Request::create('http://localhost/imprint');
        $searchRequest = Request::create('http://localhost/search');
        $searchSubRequest = Request::create('http://localhost/de/search');

        $this->assertFalse(SearchHelper::isSearchPage($randomRequest));
        $this->assertTrue(SearchHelper::isSearchPage($searchRequest));
        $this->assertTrue(SearchHelper::isSearchPage($searchSubRequest));
    }

    public function testIsNavigationPage()
    {
        $requestWithId = new Request(attributes: ['navigationId' => 'cat-id']);
        $requestWithoutId = new Request(attributes: []);

        $this->assertTrue(SearchHelper::isNavigationPage($requestWithId));
        $this->assertFalse(SearchHelper::isNavigationPage($requestWithoutId));
    }

    public function testDisableNosto()
    {
        $nostoService = new NostoService();
        $nostoService->enable();

        $salesChannelContext = $this->buildAndCreateSalesChannelContext();
        $salesChannelContext->getContext()->addExtension('nostoService', $nostoService);

        SearchHelper::disableNostoWhenEnabled($salesChannelContext);

        /** @var NostoService $adaptedNostoService */
        $adaptedNostoService = $salesChannelContext->getContext()->getExtension('nostoService');
        $this->assertFalse($adaptedNostoService->getEnabled());
    }
}
