<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search\Request\Handler\SortHandlers;

use Nosto\NostoIntegration\Search\Request\SearchNavigationRequest;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

class TopSellerSortingHandler implements SortingHandlerInterface
{
    public function supportsSorting(FieldSorting $fieldSorting): bool
    {
        return $fieldSorting->getField() === 'product.sales';
    }

    public function generateSorting(FieldSorting $fieldSorting, SearchNavigationRequest $searchNavigationRequest): void
    {
        $searchNavigationRequest->setSort('stats.orders', $fieldSorting->getDirection());
    }
}
