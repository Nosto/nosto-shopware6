<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search\Request\Handler\SortHandlers;

use Nosto\NostoIntegration\Search\Request\SearchRequest;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

class ScoreSortingHandler implements SortingHandlerInterface
{
    public function supportsSorting(FieldSorting $fieldSorting): bool
    {
        return $fieldSorting->getField() === '_score';
    }

    public function generateSorting(FieldSorting $fieldSorting, SearchRequest $searchNavigationRequest): void
    {
        // Here we do not do anything, because Nosto automatically orders by relevance.
    }
}
