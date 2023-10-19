<?php

namespace Nosto\NostoIntegration\Search\Response\GraphQL;

use Nosto\NostoIntegration\Struct\FiltersExtension;
use Nosto\NostoIntegration\Struct\Pagination;
use stdClass;

class GraphQLResponseParser
{
    public function __construct(
        private readonly stdClass $response
    ) {
    }

    public function getFiltersExtension(): FiltersExtension
    {
        return new FiltersExtension();
    }

    public function getPaginationExtension(int $limit, $offset): Pagination
    {
        return new Pagination($limit, $offset, $this->response->search->products->total);
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
