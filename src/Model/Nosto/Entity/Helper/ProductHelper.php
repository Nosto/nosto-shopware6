<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Entity\Helper;

use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\Event\ProductLoadExistingCriteriaEvent;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\Event\ProductLoadExistingParentCriteriaEvent;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\Event\ProductReloadCriteriaEvent;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\Detail\AbstractProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\CountResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ProductHelper
{
    public function __construct(
        private readonly EntityRepository $productRepository,
        private readonly AbstractProductDetailRoute $productRoute,
        private readonly EntityRepository $reviewRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ConfigProvider $configProvider,
        private readonly SeoUrlPlaceholderHandlerInterface $seoUrlReplacer,
        private readonly SalesChannelRepository $salesChannelProductRepository,
    ) {
    }

    public function getReviewsCount(SalesChannelProductEntity $product, SalesChannelContext $context): int
    {
        $reviewCriteria = new Criteria();
        $reviewCriteria->addFilter(
            new MultiFilter(MultiFilter::CONNECTION_OR, [
                new EqualsFilter('product.id', $product->getId()),
                new EqualsFilter('product.parentId', $product->getId()),
            ]),
        );
        $reviewCriteria->addAggregation(new CountAggregation('review-count', 'id'));
        $aggregation = $this->reviewRepository->aggregate($reviewCriteria, $context->getContext())->get('review-count');

        return $aggregation instanceof CountResult ? $aggregation->getCount() : 0;
    }

    public function reloadProduct(string $productId, SalesChannelContext $context): ?SalesChannelProductEntity
    {
        $criteria = $this->getCommonCriteriaWithoutChildren();
        $criteria->addFilter(new EqualsFilter('id', $productId));
        $this->eventDispatcher->dispatch(new ProductReloadCriteriaEvent($criteria, $context));

        return $this->productRoute->load($productId, new Request(), $context, $criteria)->getProduct();
    }

    private function getCommonCriteria(): Criteria
    {
        $criteria = new Criteria();
        $criteria->addAssociation('media');
        $criteria->addAssociation('cover');
        $criteria->addAssociation('options.group');
        $criteria->addAssociation('properties.group');
        $criteria->addAssociation('children.media');
        $criteria->addAssociation('children.cover');
        $criteria->addAssociation('children.options.group');
        $criteria->addAssociation('children.properties.group');
        $criteria->addAssociation('manufacturer');
        $criteria->addAssociation('manufacturer.media');
        $criteria->addAssociation('categoriesRo');

        return $criteria;
    }

    private function getCommonCriteriaWithoutChildren(): Criteria
    {
        $criteria = new Criteria();
        $criteria->addAssociation('media');
        $criteria->addAssociation('cover');
        $criteria->addAssociation('options.group');
        $criteria->addAssociation('properties.group');
        $criteria->addAssociation('manufacturer');
        $criteria->addAssociation('manufacturer.media');
        $criteria->addAssociation('categoriesRo');

        return $criteria;
    }

    public function loadExistingParentProducts(
        array $existentParentProductIds,
        SalesChannelContext $context,
    ): RepositoryIterator {
        $salesChannelId = $context->getSalesChannelId();
        $languageId = $context->getLanguageId();

        $criteria = $this->getCommonCriteria();
        $criteria->setLimit(100);
        $criteria->addAssociation('children.manufacturer');
        $criteria->addAssociation('children.manufacturer.media');
        $criteria->addAssociation('children.categoriesRo');

        if (!$this->configProvider->isEnabledSyncInactiveProducts($salesChannelId, $languageId)) {
            $criteria->addFilter(new EqualsFilter('active', true));
        }

        $categoryBlocklist = $this->configProvider->getCategoryBlocklist($salesChannelId, $languageId);
        if (count($categoryBlocklist)) {
            $criteria->addFilter(
                new NotFilter(
                    NotFilter::CONNECTION_AND,
                    [new EqualsAnyFilter('product.categoriesRo.id', $categoryBlocklist)],
                ),
            );
        }

        $criteria->addFilter(new EqualsAnyFilter('id', array_unique(array_values($existentParentProductIds))));
        $this->eventDispatcher->dispatch(new ProductLoadExistingParentCriteriaEvent($criteria, $context));

        return new RepositoryIterator($this->productRepository, $context->getContext(), $criteria);
    }

    public function getProductsIterator(
        array $productIds,
        SalesChannelContext $context,
    ): RepositoryIterator {
        $salesChannelId = $context->getSalesChannelId();
        $languageId = $context->getLanguageId();

        $criteria = new Criteria();
        $criteria->setLimit(100);
        $criteria->addFilter(new EqualsAnyFilter('id', $productIds));

        if (!$this->configProvider->isEnabledSyncInactiveProducts($salesChannelId, $languageId)) {
            $criteria->addFilter(new EqualsFilter('active', true));
        }

        $categoryBlocklist = $this->configProvider->getCategoryBlocklist($salesChannelId, $languageId);
        if (count($categoryBlocklist)) {
            $criteria->addFilter(
                new NotFilter(
                    NotFilter::CONNECTION_AND,
                    [new EqualsAnyFilter('product.categoriesRo.id', $categoryBlocklist)],
                ),
            );
        }

        $this->eventDispatcher->dispatch(new ProductLoadExistingCriteriaEvent($criteria, $context));

        return new RepositoryIterator($this->productRepository, $context->getContext(), $criteria);
    }

    /**
     * @return array<string, string>
     */
    public function loadOrderNumberMapping(array $ids, Context $context): array
    {
        $criteria = new Criteria($ids);
        $iterator = new RepositoryIterator($this->productRepository, $context, $criteria);
        $orderNumberMapping = [];
        while (($result = $iterator->fetch()) !== null) {
            foreach ($result as $product) {
                $orderNumberMapping[$product->getId()] = $product->getProductNumber();
            }
        }

        return $orderNumberMapping;
    }

    public function getProductUrl(ProductEntity $product, SalesChannelContext $context): ?string
    {
        if ($domains = $context->getSalesChannel()->getDomains()) {
            $domainId = (string) $this->configProvider->getDomainId(
                $context->getSalesChannelId(),
                $context->getLanguageId(),
            );
            $domain = $domains->has($domainId) ? $domains->get($domainId) : $domains->first();
            $raw = $this->seoUrlReplacer->generate('frontend.detail.page', [
                'productId' => $product->getId(),
            ]);
            return $this->seoUrlReplacer->replace($raw, $domain?->getUrl() ?? '', $context);
        }

        return null;
    }

    public function createRepositoryIterator(Criteria $criteria, Context $context): RepositoryIterator
    {
        return new RepositoryIterator($this->productRepository, $context, $criteria);
    }

    public function getShopwareProducts(array $productIds, SalesChannelContext $context): SalesChannelProductCollection
    {
        $criteria = $this->getCommonCriteria();
        $criteria->setIds($productIds);

        return $this->salesChannelProductRepository->search(
            $criteria,
            $context,
        )->getEntities();
    }
}
