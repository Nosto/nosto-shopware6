<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search\Request\Handler\SortHandlers;

use Nosto\NostoIntegration\Search\Request\SearchRequest;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

class PriceSortingHandler implements SortingHandlerInterface
{
    public function supportsSorting(FieldSorting $fieldSorting): bool
    {
        return $fieldSorting->getField() === 'product.cheapestPrice';
    }

    public function generateSorting(FieldSorting $fieldSorting, SearchRequest $searchNavigationRequest): void
    {
        $searchNavigationRequest->setSort('price', $fieldSorting->getDirection());
    }
}