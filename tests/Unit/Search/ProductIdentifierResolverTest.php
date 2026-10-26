<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Search;

use Nosto\NostoIntegration\Search\ProductIdentifierResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class ProductIdentifierResolverTest extends TestCase
{
    #[DataProvider('identifierMatches')]
    public function testResolvesAnExactIdentifierFromProductFieldCandidates(
        string $query,
        string $productNumber,
        ?string $ean,
        ?string $manufacturerNumber,
    ): void {
        $context = $this->createMock(SalesChannelContext::class);

        $repository = $this->createMock(SalesChannelRepository::class);
        $repository
            ->expects(self::once())
            ->method('search')
            ->with(
                self::callback(function (Criteria $criteria) use ($query): bool {
                    $filters = $criteria->getFilters();

                    if ($criteria->getTitle() !== 'Nosto.criteria'
                        || count($filters) !== 1
                        || !$filters[0] instanceof MultiFilter
                        || $filters[0]->getOperator() !== MultiFilter::CONNECTION_OR
                    ) {
                        return false;
                    }

                    [$productNumberFilter, $eanFilter, $manufacturerNumberFilter] = $filters[0]->getQueries();

                    return $productNumberFilter instanceof EqualsFilter
                        && $productNumberFilter->getField() === 'productNumber'
                        && $productNumberFilter->getValue() === trim($query)
                        && $eanFilter instanceof EqualsFilter
                        && $eanFilter->getField() === 'ean'
                        && $eanFilter->getValue() === trim($query)
                        && $manufacturerNumberFilter instanceof EqualsFilter
                        && $manufacturerNumberFilter->getField() === 'manufacturerNumber'
                        && $manufacturerNumberFilter->getValue() === trim($query);
                }),
                $context,
            )
            ->willReturn($this->createSearchResult([
                $this->createProduct('a', 'A-1'),
                $this->createProduct('b', $productNumber, $ean, $manufacturerNumber),
            ]));

        self::assertSame(
            'b',
            (new ProductIdentifierResolver($repository))->resolveFromProductFields(
                $query,
                $context,
            ),
        );
    }

    public function testDoesNotResolveAProductFieldCandidateWithoutAnExactIdentifierMatch(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $repository = $this->createMock(SalesChannelRepository::class);
        $repository
            ->expects(self::once())
            ->method('search')
            ->willReturn($this->createSearchResult([$this->createProduct('a', 'A-1')]));

        self::assertNull(
            (new ProductIdentifierResolver($repository))->resolveFromProductFields('shirt', $context),
        );
    }

    public function testLegacyLookupPrefersProductNumberOverEanAndManufacturerNumber(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $repository = $this->createMock(SalesChannelRepository::class);
        $repository
            ->expects(self::once())
            ->method('search')
            ->willReturn($this->createSearchResult([
                $this->createProduct('manufacturer', 'other', null, 'IDENTIFIER'),
                $this->createProduct('ean', 'other', 'IDENTIFIER'),
                $this->createProduct('product-number', 'IDENTIFIER'),
            ]));

        self::assertSame(
            'product-number',
            (new ProductIdentifierResolver($repository))->resolveFromProductFields('IDENTIFIER', $context),
        );
    }

    public function testLegacyLookupPrefersEanOverManufacturerNumber(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $repository = $this->createMock(SalesChannelRepository::class);
        $repository
            ->expects(self::once())
            ->method('search')
            ->willReturn($this->createSearchResult([
                $this->createProduct('manufacturer', 'other', null, 'IDENTIFIER'),
                $this->createProduct('ean', 'other', 'IDENTIFIER'),
            ]));

        self::assertSame(
            'ean',
            (new ProductIdentifierResolver($repository))->resolveFromProductFields('IDENTIFIER', $context),
        );
    }

    /**
     * @return iterable<string, array{string, string, ?string, ?string}>
     */
    public static function identifierMatches(): iterable
    {
        yield 'product number' => ['SW-123', 'SW-123', null, null];
        yield 'Unicode product number, case-insensitive' => ['äbc', 'ÄBC', null, null];
        yield 'product number with spaces' => ['ABC 123', 'ABC 123', null, null];
        yield 'EAN' => ['4012345678901', 'SW-123', '4012345678901', null];
        yield 'manufacturer number' => ['MFG-123', 'SW-123', null, 'MFG-123'];
    }

    private function createProduct(
        string $id,
        string $productNumber,
        ?string $ean = null,
        ?string $manufacturerNumber = null,
    ): ProductEntity {
        $product = new ProductEntity();
        $product->setId($id);
        $product->setProductNumber($productNumber);
        $product->setEan($ean);
        $product->setManufacturerNumber($manufacturerNumber);

        return $product;
    }

    /**
     * @param list<ProductEntity> $products
     */
    private function createSearchResult(array $products): EntitySearchResult
    {
        return new EntitySearchResult(
            'product',
            count($products),
            new ProductCollection($products),
            new AggregationResultCollection(),
            new Criteria(),
            Context::createDefaultContext(),
        );
    }
}
