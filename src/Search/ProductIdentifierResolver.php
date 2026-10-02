<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search;

use Nosto\NostoIntegration\Utils\NostoCriteriaFactory;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

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
    ) {
    }

    public function resolveFromKeywordIndex(
        string $query,
        SalesChannelContext $salesChannelContext,
    ): ?string {
        $criteria = NostoCriteriaFactory::create('criteria::resolve-search-identifier');
        $criteria->setLimit(self::CANDIDATE_LIMIT);
        $query = trim($query);

        // Query the complete identifier directly. ProductSearchBuilder tokenizes terms
        // in AND-search mode, so it cannot find identifiers containing spaces.
        $criteria->addFilter(new AndFilter([
            new EqualsFilter('product.searchKeywords.keyword', $query),
            new EqualsFilter('product.searchKeywords.languageId', $salesChannelContext->getLanguageId()),
        ]));

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
        $query = trim($query);

        return $this->isIdentifierEqual($product->getProductNumber(), $query)
            || $this->isIdentifierEqual($product->getEan(), $query)
            || $this->isIdentifierEqual($product->getManufacturerNumber(), $query);
    }

    private function isIdentifierEqual(?string $identifier, string $query): bool
    {
        return $identifier !== null
            && mb_strtolower(trim($identifier)) === mb_strtolower($query);
    }
}
