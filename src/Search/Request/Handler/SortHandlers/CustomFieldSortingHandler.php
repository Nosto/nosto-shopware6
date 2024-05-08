<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search\Request\Handler\SortHandlers;

use Nosto\NostoIntegration\Search\Request\Handler\SortingHandlerService;
use Nosto\Operation\Search\SearchOperation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

class CustomFieldSortingHandler implements SortingHandlerInterface
{
    public function supportsSorting(FieldSorting $fieldSorting): bool
    {
        return str_starts_with($fieldSorting->getField(), 'customFields');
    }

    public function generateSorting(FieldSorting $fieldSorting, SearchOperation $searchOperation): void
    {
        $searchOperation->setSort(
            SortingHandlerService::prepareCustomFieldName($fieldSorting->getField()),
            $fieldSorting->getDirection(),
        );
    }
}
