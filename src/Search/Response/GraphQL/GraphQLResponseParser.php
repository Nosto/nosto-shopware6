<?php

namespace Nosto\NostoIntegration\Search\Response\GraphQL;

use Nosto\NostoIntegration\Search\Response\GraphQL\Filter\Filter;
use Nosto\NostoIntegration\Struct\FiltersExtension;
use Nosto\NostoIntegration\Struct\IdToFieldMapping;
use Nosto\NostoIntegration\Struct\Pagination;
use Nosto\NostoIntegration\Struct\Redirect;
use Nosto\Result\Graphql\Search\SearchResult;
use Nosto\Result\Graphql\Search\SearchResult\Products\Hit;

class GraphQLResponseParser
{
    public function __construct(
        private readonly SearchResult $searchResult
    ) {
    }

    public function getFiltersExtension(): FiltersExtension
    {
        $filters = new FiltersExtension();

        foreach ($this->searchResult->getProducts()->getFacets() as $facet) {
            $filters->addFilter(Filter::getInstance($facet));
        }

        return $filters;
    }

    public function getFilterMapping(): IdToFieldMapping
    {
        $map = new IdToFieldMapping();

        foreach ($this->searchResult->getProducts()->getFacets() as $facet) {
            $map->addMapping($facet->getId(), $facet->getField());
        }

        return $map;
    }

    public function getPaginationExtension(int $limit, $offset): Pagination
    {
        return new Pagination($limit, $offset, $this->searchResult->getProducts()->getTotal());
    }

    public function getRedirectExtension(): ?Redirect
    {
        return $this->searchResult->getRedirect()
            ? new Redirect($this->searchResult->getRedirect())
            : null;
    }

    /**
     * @return string[]
     */
    public function getProductIds(): array
    {
        return array_map(
            fn (Hit $product) => $product->getProductId(),
            $this->searchResult->getProducts()->getHits()
        );
    }
}
