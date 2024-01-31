<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search\Request\Handler\SortHandlers;

use Nosto\Operation\Search\SearchOperation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

class RecommendationSortingHandler implements SortingHandlerInterface
{
    public const MERCHANDISING_SORTING_KEY = 'nosto-recommendation';

    public function supportsSorting(FieldSorting $fieldSorting): bool
    {
        return $fieldSorting->getField() === self::MERCHANDISING_SORTING_KEY;
    }

    public function generateSorting(FieldSorting $fieldSorting, SearchOperation $searchOperation): void
    {
        // Here we do not do anything, because Nosto automatically orders by relevance.
    }
}
