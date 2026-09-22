<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Search;

use Nosto\NostoIntegration\Search\ProductIdentifierResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchBuilderInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

final class ProductIdentifierResolverTest extends TestCase
{
    #[DataProvider('identifierMatches')]
    public function testResolvesAnExactIdentifierFromKeywordSearchCandidates(
        string $query,
        string $productNumber,
        ?string $ean,
        ?string $manufacturerNumber,
    ): void {
        $request = new Request([
            'search' => $query,
        ]);
        $context = $this->createMock(SalesChannelContext::class);
        $searchBuilder = $this->createMock(ProductSearchBuilderInterface::class);
        $searchBuilder
            ->expects(self::once())
            ->method('build')
            ->with(
                $request,
                self::callback(
                    static fn (Criteria $criteria): bool => $criteria->getTitle() === 'Nosto.criteria::resolve-search-identifier'
                        && $criteria->getLimit() === 100
                        && $criteria->getFilters() === [],
                ),
                $context,
            );

        $nonIdentifierMatch = $this->createProduct('a', 'A-1');
        $identifierMatch = $this->createProduct('b', $productNumber, $ean, $manufacturerNumber);
        $repository = $this->createMock(SalesChannelRepository::class);
        $repository
            ->expects(self::once())
            ->method('search')
            ->with(self::isInstanceOf(Criteria::class), $context)
            ->willReturn($this->createSearchResult([$nonIdentifierMatch, $identifierMatch]));

        $resolver = new ProductIdentifierResolver($repository, $searchBuilder);

        self::assertSame('b', $resolver->resolveFromKeywordIndex($query, $request, $context));
    }

    public function testDoesNotResolveAKeywordCandidateWithoutAnExactIdentifierMatch(): void
    {
        $request = new Request([
            'search' => 'shirt',
        ]);
        $context = $this->createMock(SalesChannelContext::class);
        $searchBuilder = $this->createMock(ProductSearchBuilderInterface::class);
        $repository = $this->createMock(SalesChannelRepository::class);
        $repository
            ->expects(self::once())
            ->method('search')
            ->willReturn($this->createSearchResult([$this->createProduct('a', 'A-1')]));

        self::assertNull(
            (new ProductIdentifierResolver($repository, $searchBuilder))->resolveFromKeywordIndex(
                'shirt',
                $request,
                $context,
            ),
        );
    }

    /**
     * @return iterable<string, array{string, string, ?string, ?string}>
     */
    public static function identifierMatches(): iterable
    {
        yield 'product number' => ['SW-123', 'SW-123', null, null];
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
