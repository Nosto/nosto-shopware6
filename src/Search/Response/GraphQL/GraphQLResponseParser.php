<?php

namespace Nosto\NostoIntegration\Search\Response\GraphQL;

use Nosto\NostoIntegration\Search\Response\GraphQL\Filter\Filter;
use Nosto\NostoIntegration\Struct\FiltersExtension;
use Nosto\NostoIntegration\Struct\IdToFieldMapping;
use Nosto\NostoIntegration\Struct\Pagination;
use Nosto\NostoIntegration\Struct\Redirect;
use stdClass;

class GraphQLResponseParser
{
    public function __construct(
        private readonly stdClass $response
    ) {
    }

    public function getFiltersExtension(): FiltersExtension
    {
        $filters = new FiltersExtension();

        foreach ($this->response->search->products->facets as $facet) {
            $filters->addFilter(Filter::getInstance($facet));
        }

        return $filters;
    }

    public function getFilterMapping(): IdToFieldMapping
    {
        $map = new IdToFieldMapping();

        foreach ($this->response->search->products->facets as $facet) {
            $map->addMapping($facet->id, $facet->field);
        }

        return $map;
    }

    public function getPaginationExtension(int $limit, $offset): Pagination
    {
        return new Pagination($limit, $offset, $this->response->search->products->total);
    }

    public function getRedirectExtension(): ?Redirect
    {
        return $this->response->search->redirect
            ? new Redirect($this->response->search->redirect)
            : null;
    }

    /**
     * @return string[]
     */
    public function getProductIds(): array
    {
        return array_map(
            fn ($product) => $product->productId,
            $this->response->search->products->hits
        );
    }
}
