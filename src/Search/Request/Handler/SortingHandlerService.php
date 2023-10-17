<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search\Request\Handler;

use Nosto\NostoIntegration\Search\Request\Handler\SortHandlers\PriceSortingHandler;
use Nosto\NostoIntegration\Search\Request\Handler\SortHandlers\ProductNameSortingHandler;
use Nosto\NostoIntegration\Search\Request\Handler\SortHandlers\ReleaseDateSortingHandler;
use Nosto\NostoIntegration\Search\Request\Handler\SortHandlers\ScoreSortingHandler;
use Nosto\NostoIntegration\Search\Request\Handler\SortHandlers\SortingHandlerInterface;
use Nosto\NostoIntegration\Search\Request\Handler\SortHandlers\TopSellerSortingHandler;
use Nosto\NostoIntegration\Search\Request\SearchNavigationRequest;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class SortingHandlerService
{
    public function handle(SearchNavigationRequest $searchNavigationRequest, Criteria $criteria): void
    {
        foreach ($this->getSortingHandlers() as $handler) {
            foreach ($criteria->getSorting() as $fieldSorting) {
                if ($handler->supportsSorting($fieldSorting)) {
                    $handler->generateSorting($fieldSorting, $searchNavigationRequest);
                }
            }
        }
    }

    /**
     * @return SortingHandlerInterface[]
     */
    protected function getSortingHandlers(): array
    {
        return [
            new ScoreSortingHandler(),
            new PriceSortingHandler(),
            new ProductNameSortingHandler(),
            new ReleaseDateSortingHandler(),
            new TopSellerSortingHandler(),
        ];
    }
}
