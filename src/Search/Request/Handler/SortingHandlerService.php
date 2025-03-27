<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search\Request\Handler;

use Nosto\NostoIntegration\Search\Request\Handler\SortHandlers\CustomFieldSortingHandler;
use Nosto\NostoIntegration\Search\Request\Handler\SortHandlers\PriceSortingHandler;
use Nosto\NostoIntegration\Search\Request\Handler\SortHandlers\ProductNameSortingHandler;
use Nosto\NostoIntegration\Search\Request\Handler\SortHandlers\ProductNumberSortingHandler;
use Nosto\NostoIntegration\Search\Request\Handler\SortHandlers\RatingSortingHandler;
use Nosto\NostoIntegration\Search\Request\Handler\SortHandlers\RecommendationSortingHandler;
use Nosto\NostoIntegration\Search\Request\Handler\SortHandlers\ReleaseDateSortingHandler;
use Nosto\NostoIntegration\Search\Request\Handler\SortHandlers\ScoreSortingHandler;
use Nosto\NostoIntegration\Search\Request\Handler\SortHandlers\SortingHandlerInterface;
use Nosto\NostoIntegration\Search\Request\Handler\SortHandlers\StockSortingHandler;
use Nosto\NostoIntegration\Search\Request\Handler\SortHandlers\TopSellerSortingHandler;
use Nosto\Operation\Search\SearchOperation;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class SortingHandlerService
{
    public function __construct(
        private readonly EntityRepository $sortingRepository,
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public function handle(SearchOperation $searchOperation, Criteria $criteria): void
    {
        foreach ($this->getSortingHandlers() as $handler) {
            foreach ($criteria->getSorting() as $fieldSorting) {
                if ($handler->supportsSorting($fieldSorting)) {
                    $handler->generateSorting($fieldSorting, $searchOperation);
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
            new CustomFieldSortingHandler(),
            new PriceSortingHandler(),
            new ProductNameSortingHandler(),
            new ProductNumberSortingHandler(),
            new RatingSortingHandler(),
            new ReleaseDateSortingHandler(),
            new ScoreSortingHandler(),
            new RecommendationSortingHandler(),
            new StockSortingHandler(),
            new TopSellerSortingHandler(),
        ];
    }

    public static function prepareCustomFieldName(string $field): string
    {
        [$delimiter, $fieldKey] = explode('.', $field);

        return $delimiter . '.' . mb_strtolower($fieldKey);
    }

    public function getDefaultSortingKey(string $key, SalesChannelContext $context): ?string
    {
        $id = $this->systemConfigService->getString($key, $context->getSalesChannelId());

        if (!Uuid::isValid($id)) {
            return $id;
        }

        $criteria = new Criteria([$id]);

        return $this->sortingRepository->search($criteria, $context->getContext())->first()?->get('key');
    }

    public function getNostoSortingPriority(SalesChannelContext $context): ?int
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('key', RecommendationSortingHandler::MERCHANDISING_SORTING_KEY));
        $criteria->setLimit(1);
        $sorting = $this->sortingRepository->search($criteria, $context->getContext())->first();

        return $sorting?->getPriority();
    }
}
