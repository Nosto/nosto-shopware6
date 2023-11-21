<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search\Request\Handler\SortHandlers;

use Nosto\Operation\Search\SearchOperation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

interface SortingHandlerInterface
{
    public function supportsSorting(FieldSorting $fieldSorting): bool;

    public function generateSorting(
        FieldSorting $fieldSorting,
        SearchOperation $searchOperation,
    ): void;
}
