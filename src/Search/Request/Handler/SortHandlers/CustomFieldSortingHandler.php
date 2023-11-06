<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search\Request\Handler\SortHandlers;

use Nosto\NostoIntegration\Search\Request\SearchRequest;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

class CustomFieldSortingHandler implements SortingHandlerInterface
{
    public function supportsSorting(FieldSorting $fieldSorting): bool
    {
        return str_starts_with($fieldSorting->getField(), 'customFields');
    }

    public function generateSorting(FieldSorting $fieldSorting, SearchRequest $searchNavigationRequest): void
    {
        $searchNavigationRequest->setSort($fieldSorting->getField(), $fieldSorting->getDirection());
    }
}
