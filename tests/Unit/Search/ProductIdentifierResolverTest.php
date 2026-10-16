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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class ProductIdentifierResolverTest extends TestCase
{
    #[DataProvider('identifierMatches')]
    public function testResolvesAnExactIdentifierFromKeywordSearchCandidates(
        string $query,
        string $productNumber,
        ?string $ean,
        ?string $manufacturerNumber,
    ): void {
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getLanguageId')->willReturn('language-id');

        $repository = $this->createMock(SalesChannelRepository::class);
        $repository
            ->expects(self::once())
            ->method('search')
            ->with(
                self::callback(function (Criteria $criteria) use ($query): bool {
                    $filters = $criteria->getFilters();

                    if ($criteria->getTitle() !== 'Nosto.criteria::resolve-search-identifier'
                        || $criteria->getLimit() !== 100
                        || count($filters) !== 1
                        || !$filters[0] instanceof AndFilter
                    ) {
                        return false;
                    }

                    [$keywordFilter, $languageFilter] = $filters[0]->getQueries();

                    return $keywordFilter instanceof EqualsFilter
                        && $keywordFilter->getField() === 'product.searchKeywords.keyword'
                        && $keywordFilter->getValue() === trim($query)
                        && $languageFilter instanceof EqualsFilter
                        && $languageFilter->getField() === 'product.searchKeywords.languageId'
                        && $languageFilter->getValue() === 'language-id';
                }),
                $context,
            )
            ->willReturn($this->createSearchResult([
                $this->createProduct('a', 'A-1'),
                $this->createProduct('b', $productNumber, $ean, $manufacturerNumber),
            ]));

        self::assertSame(
            'b',
            (new ProductIdentifierResolver($repository))->resolveFromKeywordIndex(
                $query,
                $context,
            ),
        );
    }

    public function testDoesNotResolveAKeywordCandidateWithoutAnExactIdentifierMatch(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getLanguageId')->willReturn('language-id');
        $repository = $this->createMock(SalesChannelRepository::class);
        $repository
            ->expects(self::once())
            ->method('search')
            ->willReturn($this->createSearchResult([$this->createProduct('a', 'A-1')]));

        self::assertNull(
            (new ProductIdentifierResolver($repository))->resolveFromKeywordIndex('shirt', $context),
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
