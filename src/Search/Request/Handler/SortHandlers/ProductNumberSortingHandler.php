<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search\Request\Handler\SortHandlers;

use Nosto\Operation\Search\SearchOperation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

class ProductNumberSortingHandler implements SortingHandlerInterface
{
    public function supportsSorting(FieldSorting $fieldSorting): bool
    {
        return $fieldSorting->getField() === 'product.productNumber';
    }

    public function generateSorting(FieldSorting $fieldSorting, SearchOperation $searchOperation): void
    {
        $searchOperation->setSort('customFields.productNumber', $fieldSorting->getDirection());
    }
}
