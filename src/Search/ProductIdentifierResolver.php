<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search;

use Nosto\NostoIntegration\Utils\NostoCriteriaFactory;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchBuilderInterface;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves an exact product identifier through Shopware's search-keyword index.
 *
 * This makes the product selected for a Nosto search result match Shopware's
 * autocomplete lookup, including inherited product numbers.
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

    private function isExactIdentifierMatch(ProductEntity $product, string $query): bool
    {
        return $this->isIdentifierEqual($product->getProductNumber(), $query)
            || $this->isIdentifierEqual($product->getEan(), $query)
            || $this->isIdentifierEqual($product->getManufacturerNumber(), $query);
    }

    private function isIdentifierEqual(?string $identifier, string $query): bool
    {
        return $identifier !== null
            && strcasecmp(trim($identifier), trim($query)) === 0;
    }
}
