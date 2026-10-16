<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Search\Request\Handler;

use Nosto\NostoIntegration\Search\Request\Handler\CanonicalFilterUrlBuilder;
use Nosto\NostoIntegration\Struct\IdToFieldMapping;
use PHPUnit\Framework\TestCase;

final class CanonicalFilterUrlBuilderTest extends TestCase
{
    private function mapping(): IdToFieldMapping
    {
        return new IdToFieldMapping([
            'facet-b' => 'brand',
            'facet-c' => 'skus.customFields.colour',
            'facet-p' => 'price',
        ]);
    }

    private function build(?string $queryString, ?IdToFieldMapping $mapping = null): ?string
    {
        return (new CanonicalFilterUrlBuilder())->build(
            '/search',
            $queryString,
            $mapping ?? $this->mapping(),
        );
    }

    public function testRewritesValueFilterAndPreservesOtherParametersVerbatim(): void
    {
        self::assertSame(
            '/search?search=main&facet-b=Shopware+Fashion',
            $this->build('search=main&products.filter.brand=Shopware+Fashion'),
        );
    }

    public function testConvertsDoublePipeToSinglePipe(): void
    {
        self::assertSame(
            '/search?facet-c=Blue%7CRed',
            $this->build('products.filter.skus.customFields.colour=Blue%7C%7CRed'),
        );
    }

    public function testRangeBecomesMinAndMaxParameters(): void
    {
        self::assertSame(
            '/search?min-facet-p=10&max-facet-p=20',
            $this->build('products.filter.price=10%7E20'),
        );
    }

    public function testOpenEndedRangeEmitsOnlyThePresentBound(): void
    {
        self::assertSame(
            '/search?min-facet-p=10',
            $this->build('products.filter.price=10%7E'),
        );
    }

    public function testExclusiveRangeStillCanonicalises(): void
    {
        self::assertSame(
            '/search?min-facet-p=10&max-facet-p=20',
            $this->build('products.filter.price=%5B10%7E20%5D'),
        );
    }

    public function testReturnsNullWithoutFieldPathParameters(): void
    {
        self::assertNull($this->build('search=main&sort=name-asc'));
    }

    public function testReturnsNullWithoutMapping(): void
    {
        self::assertNull(
            (new CanonicalFilterUrlBuilder())->build(
                '/search',
                'search=main&products.filter.brand=Shopware+Fashion',
                null,
            ),
        );
    }

    public function testReturnsNullWhenFieldDoesNotResolve(): void
    {
        self::assertNull($this->build('search=main&products.filter.unknownField=x'));
    }

    public function testKeepsUnresolvableFieldVerbatimBesideARewrittenOne(): void
    {
        self::assertSame(
            '/search?products.filter.unknownField=x&facet-b=X',
            $this->build('products.filter.unknownField=x&products.filter.brand=X'),
        );
    }

    public function testOutputIsStableWhenFedBackIn(): void
    {
        $canonical = $this->build('search=main&products.filter.brand=Shopware+Fashion');

        self::assertSame('/search?search=main&facet-b=Shopware+Fashion', $canonical);
        self::assertNull($this->build('search=main&facet-b=Shopware+Fashion'));
    }

    public function testSortAndPaginationParametersKeepTheirPlace(): void
    {
        self::assertSame(
            '/search?search=main&order=price-asc&p=2&facet-b=X',
            $this->build('search=main&order=price-asc&p=2&products.filter.brand=X'),
        );
    }
}
