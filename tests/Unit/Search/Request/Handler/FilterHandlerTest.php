<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Search\Request\Handler;

use Nosto\Model\Signup\Account;
use Nosto\NostoIntegration\Search\Request\Handler\FilterHandler;
use Nosto\NostoIntegration\Search\Response\GraphQL\Filter\LabelTextFilter;
use Nosto\NostoIntegration\Service\FilterPayloadService;
use Nosto\NostoIntegration\Struct\FiltersExtension;
use Nosto\NostoIntegration\Struct\IdToFieldMapping;
use Nosto\Operation\Search\SearchOperation;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\HttpFoundation\Request;

final class FilterHandlerTest extends TestCase
{
    private function handler(): FilterHandler
    {
        return new FilterHandler($this->createMock(FilterPayloadService::class));
    }

    private function searchOperation(): SearchOperation
    {
        return new SearchOperation(new Account('test-account'));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function appliedFilters(SearchOperation $operation): array
    {
        return $operation->getVariables()['filter'] ?? [];
    }

    public function testAppliesFieldPathValueFilterFromRealRequest(): void
    {
        $operation = $this->searchOperation();
        $request = Request::create('/search?search=main&products.filter.brand=Shopware+Fashion');

        $this->handler()->handleFilters($request, new Criteria(), $operation);

        self::assertSame(
            [[
                'field' => 'brand',
                'value' => ['Shopware Fashion'],
            ]],
            $this->appliedFilters($operation),
        );
    }

    public function testAppliesNestedSkuCustomFieldFilter(): void
    {
        $operation = $this->searchOperation();
        $request = Request::create('/search?products.filter.skus.customFields.colour=Blue%7C%7CRed');

        $this->handler()->handleFilters($request, new Criteria(), $operation);

        self::assertSame(
            [[
                'field' => 'skus.customFields.colour',
                'value' => ['Blue', 'Red'],
            ]],
            $this->appliedFilters($operation),
        );
    }

    public function testAppliesRangeFilter(): void
    {
        $operation = $this->searchOperation();
        $request = Request::create('/search?products.filter.price=%5B10%7E20%5D');

        $this->handler()->handleFilters($request, new Criteria(), $operation);

        self::assertSame(
            [[
                'field' => 'price',
                'range' => [
                    'gt' => '10',
                    'lt' => '20',
                ],
            ]],
            $this->appliedFilters($operation),
        );
    }

    public function testIgnoresShopwareQueryParameters(): void
    {
        $operation = $this->searchOperation();
        $request = Request::create('/search?search=main&sort=name-asc&p=2');

        $this->handler()->handleFilters($request, new Criteria(), $operation);

        self::assertSame([], $this->appliedFilters($operation));
    }

    public function testAppliesFieldPathFilterOnExistingRequestPath(): void
    {
        $operation = $this->searchOperation();
        $request = Request::create('/search?products.filter.brand=Shopware+Fashion');

        // $newRequest = false is the cookie-backed path; the field-path filter must still
        // apply even though no filter-mapping cookie is present.
        $this->handler()->handleFilters($request, new Criteria(), $operation, false);

        self::assertSame(
            [[
                'field' => 'brand',
                'value' => ['Shopware Fashion'],
            ]],
            $this->appliedFilters($operation),
        );
    }

    public function testAppliesOnlyTheFirstOfMultipleRanges(): void
    {
        // SearchOperation merges ranges per field, so only the first is representable.
        $operation = $this->searchOperation();
        $request = Request::create('/search?products.filter.price=10%7E20,30%7E40');

        $this->handler()->handleFilters($request, new Criteria(), $operation);

        self::assertSame(
            [[
                'field' => 'price',
                'range' => [
                    'gt' => '10',
                    'lt' => '20',
                ],
            ]],
            $this->appliedFilters($operation),
        );
    }

    public function testRoutesRatingFieldToARangeFilter(): void
    {
        $operation = $this->searchOperation();
        $request = Request::create('/search?products.filter.ratingValue=4');

        $this->handler()->handleFilters($request, new Criteria(), $operation);

        self::assertSame(
            [[
                'field' => 'ratingValue',
                'range' => [
                    'gt' => '4',
                ],
            ]],
            $this->appliedFilters($operation),
        );
    }

    public function testKeepsTheFirstRangeWhenAFieldIsRepeated(): void
    {
        $operation = $this->searchOperation();
        $request = Request::create('/search?products.filter.price=10%7E20&products.filter.price=30%7E40');

        $this->handler()->handleFilters($request, new Criteria(), $operation);

        self::assertSame(
            [[
                'field' => 'price',
                'range' => [
                    'gt' => '10',
                    'lt' => '20',
                ],
            ]],
            $this->appliedFilters($operation),
        );
    }

    public function testAppliesFieldPathFilterWithNoFacetMapping(): void
    {
        $operation = $this->searchOperation();
        $request = Request::create('/search?products.filter.brand=Shopware+Fashion');

        $this->handler()->handleFilters($request, new Criteria(), $operation);

        self::assertSame(
            [[
                'field' => 'brand',
                'value' => ['Shopware Fashion'],
            ]],
            $this->appliedFilters($operation),
        );
    }

    public function testFieldPathOnlyRequestSkipsTheCookiePayloadLookup(): void
    {
        // This is what withoutFieldPathParameters() actually buys. PHP also exposes each
        // field-path parameter under a mangled 'products_filter_*' name; if those were left in
        // $selectedFilters, a request carrying only field-path filters would not take the early
        // return and would pay a cookie-payload lookup (a DB read) it has no use for.
        $payloadService = $this->createMock(FilterPayloadService::class);
        $payloadService->expects(self::never())->method('resolveCookiePayload');

        $operation = $this->searchOperation();
        $request = Request::create('/search?products.filter.brand=Shopware+Fashion');

        // $newRequest = false is the cookie-backed path, the only one that reads the payload.
        (new FilterHandler($payloadService))->handleFilters($request, new Criteria(), $operation, false);

        // The filter itself must still be applied exactly once.
        self::assertSame(
            [[
                'field' => 'brand',
                'value' => ['Shopware Fashion'],
            ]],
            $this->appliedFilters($operation),
        );
    }

    public function testAppliesEachSourceOnceOnALiveSerpShapedRequest(): void
    {
        // On a live SERP the facet is in the 'nostoFilters' extension and mapped to the same
        // field a field-path parameter targets. Both sources must contribute exactly once.
        $operation = $this->searchOperation();

        $criteria = new Criteria();
        $criteria->addExtension('nostoFilterMapping', new IdToFieldMapping([
            'facet-123' => 'brand',
        ]));
        $criteria->addExtension('nostoFilters', (new FiltersExtension())->addFilter(
            new LabelTextFilter('facet-123', 'Brand', 'brand'),
        ));

        $request = Request::create(
            '/search?facet-123=Shopware+Freetime&products.filter.brand=Shopware+Fashion',
        );

        $this->handler()->handleFilters($request, $criteria, $operation);

        self::assertSame(
            [[
                'field' => 'brand',
                'value' => ['Shopware Fashion', 'Shopware Freetime'],
            ]],
            $this->appliedFilters($operation),
        );
    }

    public function testAppliesEachSourceOnceWhenBothAFacetIdAndAFieldPathArePresent(): void
    {
        // A facet-id parameter and a field-path parameter for the same field must each
        // contribute exactly one value: two distinct sources, no duplication of either.
        $operation = $this->searchOperation();

        $criteria = new Criteria();
        $criteria->addExtension('nostoFilterMapping', new IdToFieldMapping([
            'facet-123' => 'brand',
        ]));
        $criteria->addExtension('nostoFilters', (new FiltersExtension())->addFilter(
            new LabelTextFilter('facet-123', 'Brand', 'brand'),
        ));

        $request = Request::create('/search?facet-123=Shopware+Freetime&products.filter.brand=Shopware+Fashion');

        $this->handler()->handleFilters($request, $criteria, $operation);

        self::assertSame(
            [[
                'field' => 'brand',
                'value' => ['Shopware Fashion', 'Shopware Freetime'],
            ]],
            $this->appliedFilters($operation),
        );
    }
}
