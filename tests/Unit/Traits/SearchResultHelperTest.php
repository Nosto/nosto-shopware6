<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Traits;

use Nosto\NostoIntegration\Struct\Pagination;
use Nosto\NostoIntegration\Traits\SearchResultHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class SearchResultHelperTest extends TestCase
{
    #[DataProvider('searchResults')]
    public function testListingAndStorefrontPaginationTotals(
        int $nostoTotal,
        int $offset,
        bool $productIsVisible,
        int $expectedTotal,
    ): void {
        $productId = 'product-id';
        $criteria = new Criteria([$productId]);
        $criteria->addExtension('nostoPagination', new Pagination(24, $offset, $nostoTotal));
        $products = new ProductCollection();
        if ($productIsVisible) {
            $product = new ProductEntity();
            $product->setId($productId);
            $products->add($product);
        }

        $repository = $this->createMock(SalesChannelRepository::class);
        $repository->method('search')->willReturnCallback(
            static fn (Criteria $databaseCriteria): EntitySearchResult => new EntitySearchResult(
                'product',
                $products->count(),
                $products,
                null,
                $databaseCriteria,
                Context::createDefaultContext(),
            ),
        );
        $helper = new class($repository) {
            use SearchResultHelper {
                fetchProducts as public;
            }

            public function __construct(
                private readonly SalesChannelRepository $salesChannelProductRepository,
            ) {
            }
        };

        $result = $helper->fetchProducts($criteria, $this->createMock(SalesChannelContext::class));

        self::assertSame($productIsVisible ? 1 : 0, $result->count());
        self::assertSame($expectedTotal, $result->getTotal());
        $pagination = $result->getCriteria()->getExtension('nostoPagination');
        self::assertInstanceOf(Pagination::class, $pagination);
        self::assertSame($expectedTotal, $pagination->getTotal());
        self::assertSame($offset, $pagination->getOffset());
        self::assertSame(24, $pagination->getLimit());
    }

    public static function searchResults(): iterable
    {
        yield 'sole hit filtered out' => [1, 0, false, 0];
        yield 'sole hit visible' => [1, 0, true, 1];
        yield 'more hits may exist on later pages' => [25, 0, false, 25];
        yield 'empty page beyond the last hit' => [1, 24, false, 1];
    }
}
