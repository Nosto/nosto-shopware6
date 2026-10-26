<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search;

use Nosto\NostoIntegration\Utils\NostoCriteriaFactory;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Resolves an exact product identifier from the product fields used by Shopware search.
 */
class ProductIdentifierResolver
{
    public function __construct(
        private readonly SalesChannelRepository $salesChannelProductRepository,
    ) {
    }

    public function resolveFromProductFields(string $query, SalesChannelContext $salesChannelContext): ?string
    {
        $query = trim($query);
        $criteria = NostoCriteriaFactory::create();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('productNumber', $query),
            new EqualsFilter('ean', $query),
            new EqualsFilter('manufacturerNumber', $query),
        ]));

        return $this->resolveExactIdentifierFromCandidates(
            $this->salesChannelProductRepository->search($criteria, $salesChannelContext)->getEntities(),
            $query,
        );
    }

    /**
     * @param iterable<ProductEntity> $products
     */
    private function resolveExactIdentifierFromCandidates(iterable $products, string $query): ?string
    {
        $query = trim($query);
        $eanMatch = null;
        $manufacturerNumberMatch = null;

        foreach ($products as $product) {
            // Product numbers are the primary identifier. Check every candidate before
            // falling back, so repository ordering cannot make a manufacturer number win.
            if ($this->isIdentifierEqual($product->getProductNumber(), $query)) {
                return $product->getId();
            }

            if ($eanMatch === null && $this->isIdentifierEqual($product->getEan(), $query)) {
                $eanMatch = $product->getId();
            }

            if ($manufacturerNumberMatch === null
                && $this->isIdentifierEqual($product->getManufacturerNumber(), $query)
            ) {
                $manufacturerNumberMatch = $product->getId();
            }
        }

        return $eanMatch ?? $manufacturerNumberMatch;
    }

    private function isIdentifierEqual(?string $identifier, string $query): bool
    {
        return $identifier !== null
            && mb_strtolower(trim($identifier)) === mb_strtolower($query);
    }
}
