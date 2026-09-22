<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search;

use Nosto\NostoIntegration\Utils\NostoCriteriaFactory;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves an exact product identifier through Shopware's search-keyword index.
 *
 * The keyword index contains values from more fields than just product identifiers,
 * so candidates are checked against the three identifier fields before returning one.
 */
class ProductIdentifierResolver
{
    private const CANDIDATE_LIMIT = 100;

    public function __construct(
        private readonly SalesChannelRepository $salesChannelProductRepository,
        private readonly ProductSearchBuilderInterface $searchBuilder,
    ) {
    }

    public function resolveFromKeywordIndex(
        string $query,
        Request $request,
        SalesChannelContext $salesChannelContext,
    ): ?string {
        $criteria = NostoCriteriaFactory::create('criteria::resolve-search-identifier');
        $criteria->setLimit(self::CANDIDATE_LIMIT);

        // ProductSearchBuilder queries product_search_keyword, which already contains
        // inherited product number, EAN and manufacturer-number values.
        $this->searchBuilder->build($request, $criteria, $salesChannelContext);

        foreach ($this->salesChannelProductRepository->search(
            $criteria,
            $salesChannelContext,
        )->getEntities() as $product) {
            if ($this->isExactIdentifierMatch($product, $query)) {
                return $product->getId();
            }
        }

        return null;
    }

    /**
     * Retains the legacy lookup for merchants that have not enabled the indexed implementation.
     */
    public function resolveFromProductFields(string $query, SalesChannelContext $salesChannelContext): ?string
    {
        $criteria = NostoCriteriaFactory::create();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('productNumber', $query),
            new EqualsFilter('ean', $query),
            new EqualsFilter('manufacturerNumber', $query),
        ]));

        return $this->salesChannelProductRepository->search($criteria, $salesChannelContext)->first()?->getId();
    }

    private function isExactIdentifierMatch(ProductEntity $product, string $query): bool
    {
        return $product->getProductNumber() === $query
            || $product->getEan() === $query
            || $product->getManufacturerNumber() === $query;
    }
}
