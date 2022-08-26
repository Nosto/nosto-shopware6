<?php

declare(strict_types=1);

namespace Od\NostoIntegration\Model\Nosto\Entity\Helper;

use Od\NostoIntegration\Model\ConfigProvider;
use Od\NostoIntegration\Model\Nosto\Entity\Product\Event\ProductLoadExistingCriteriaEvent;
use Od\NostoIntegration\Model\Nosto\Entity\Product\Event\ProductLoadExistingParentCriteriaEvent;
use Od\NostoIntegration\Model\Nosto\Entity\Product\Event\ProductReloadCriteriaEvent;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\CountResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;


class ProductHelper
{
    private SalesChannelRepositoryInterface $productRepository;
    private EntityRepositoryInterface $reviewRepository;
    private EventDispatcherInterface $eventDispatcher;
    private ConfigProvider $configProvider;

    public function __construct(
        SalesChannelRepositoryInterface $productRepository,
        EntityRepositoryInterface $reviewRepository,
        EventDispatcherInterface $eventDispatcher,
        ConfigProvider $configProvider
    ) {
        $this->productRepository = $productRepository;
        $this->reviewRepository = $reviewRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->configProvider = $configProvider;
    }

    public function getReviewsCount(SalesChannelProductEntity $product, SalesChannelContext $context): int
    {
        $reviewCriteria = new Criteria();
        $reviewCriteria->addFilter(
            new MultiFilter(MultiFilter::CONNECTION_OR, [
                new EqualsFilter('product.id', $product->getId()),
                new EqualsFilter('product.parentId', $product->getId()),
            ])
        );
        $reviewCriteria->addAggregation(new CountAggregation('review-count', 'id'));
        $aggregation = $this->reviewRepository->aggregate($reviewCriteria, $context->getContext())->get('review-count');

        return $aggregation instanceof CountResult ? $aggregation->getCount() : 0;
    }

    public function reloadProduct(string $productId, SalesChannelContext $context): ?ProductEntity
    {
        $criteria = $this->getCommonCriteria();
        $criteria->addFilter(new EqualsFilter('id', $productId));
        $this->eventDispatcher->dispatch(new ProductReloadCriteriaEvent($criteria, $context));
        return $this->productRepository->search($criteria, $context)->first();
    }

    private function getCommonCriteria(): Criteria
    {
        $criteria = new Criteria();
        $criteria->addAssociation('media');
        $criteria->addAssociation('options.group');
        $criteria->addAssociation('properties.group');
        $criteria->addAssociation('children.media');
        $criteria->addAssociation('children.options.group');
        $criteria->addAssociation('children.properties.group');
        $criteria->addAssociation('manufacturer');
        $criteria->addAssociation('categoriesRo');
        return $criteria;
    }

    public function loadExistingParentProducts(
        array $existentParentProductIds,
        SalesChannelContext $context
    ): EntitySearchResult {
        $criteria = $this->getCommonCriteria();
        $criteria->addAssociation('children.manufacturer');
        $criteria->addAssociation('children.categoriesRo');

        if (!$this->configProvider->isEnabledSyncInactiveProducts($context->getSalesChannelId())) {
            $criteria->addFilter(new EqualsFilter('active', true));
        }

        $criteria->addFilter(new EqualsAnyFilter('id', array_unique(array_values($existentParentProductIds))));
        $this->eventDispatcher->dispatch(new ProductLoadExistingParentCriteriaEvent($criteria, $context));
        return $this->productRepository->search($criteria, $context);
    }

    public function loadProducts(
        array $productIds,
        SalesChannelContext $context
    ): ProductCollection {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('id', $productIds));
        if (!$this->configProvider->isEnabledSyncInactiveProducts($context->getSalesChannelId())) {
            $criteria->addFilter(new EqualsFilter('active', true));
        }
        $this->eventDispatcher->dispatch(new ProductLoadExistingCriteriaEvent($criteria, $context));
        return $this->productRepository->search($criteria, $context)->getEntities();
    }
}
