<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Traits;

use Nosto\NostoIntegration\Struct\Pagination;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

trait SearchResultHelper
{
    protected function createEmptySearchResult(Criteria $criteria, Context $context): EntitySearchResult
    {
        // Return an empty response, as Shopware would search for all products if no explicit
        // product ids are submitted.
        return new EntitySearchResult(
            ProductEntity::class,
            0,
            new EntityCollection(),
            new AggregationResultCollection(),
            $criteria,
            $context,
        );
    }

    protected function fetchProducts(
        Criteria $criteria,
        SalesChannelContext $salesChannelContext,
        ?string $query = null,
    ): EntitySearchResult {
        if ($query !== null && count($criteria->getIds()) === 1) {
            $this->modifyCriteriaFromQuery($query, $criteria, $salesChannelContext);
        }

        $result = $this->salesChannelProductRepository->search(
            $this->cleanDatabaseCriteria($criteria),
            $salesChannelContext,
        );

        return $this->fixResultOrder($result, $criteria);
    }

    /**
     * When search results are fetched from the database, we don't want to have any filters or limits.
     * All the filtering/limiting is already done within the API. We need the extensions and the associations.
     */
    private function cleanDatabaseCriteria(Criteria $criteria): Criteria
    {
        $productCriteria = clone $criteria;
        $productCriteria->setOffset(0);
        $productCriteria->resetQueries();
        $productCriteria->resetFilters();
        $productCriteria->resetSorting();
        $productCriteria->resetAggregations();

        return $productCriteria;
    }

    /**
     * When search results are fetched from the database, the ordering of the products is based on the
     * database structure, which is not what we want. We manually re-order them by the ID, so the
     * ordering matches the result that the Nosto API returned.
     */
    private function fixResultOrder(EntitySearchResult $result, Criteria $criteria): EntitySearchResult
    {
        if (!$result->count()) {
            return $result;
        }

        $sortedElements = $this->sortElementsByIdArray($result->getElements(), $criteria->getIds());
        $result->clear();

        foreach ($sortedElements as $element) {
            $result->add($element);
        }

        return $result;
    }

    private function sortElementsByIdArray(array $elements, array $ids): array
    {
        $sorted = [];

        foreach ($ids as $id) {
            if (is_array($id)) {
                $id = implode('-', $id);
            }

            if (array_key_exists($id, $elements)) {
                $sorted[$id] = $elements[$id];
            }
        }

        return $sorted;
    }

    private function getPage(Request $request): int
    {
        $page = $request->query->getInt('p', 1);

        if ($request->isMethod(Request::METHOD_POST)) {
            $page = $request->request->getInt('p', $page);
        }

        return $page <= 0 ? 1 : $page;
    }

    public function getOffset(Request $request, ?int $limit = null): float|int
    {
        if (!$limit) {
            $limit = Pagination::DEFAULT_LIMIT;
        }

        $page = $this->getPage($request);

        return ($page - 1) * $limit;
    }

    /**
     * If a specific variant is searched by its product number, we want to modify the criteria
     * to show that variant instead of the main product.
     */
    private function modifyCriteriaFromQuery(
        string $query,
        Criteria $criteria,
        SalesChannelContext $salesChannelContext,
    ): void {
        $productCriteria = new Criteria();
        $productCriteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('productNumber', $query),
            new EqualsFilter('ean', $query),
            new EqualsFilter('manufacturerNumber', $query),
        ]));
        $product = $this->salesChannelProductRepository->search($productCriteria, $salesChannelContext)->first();
        if ($product) {
            $criteria->setIds([$product->getId()]);
        }
    }
}
