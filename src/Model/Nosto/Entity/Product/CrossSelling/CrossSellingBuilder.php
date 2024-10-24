<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Entity\Product\CrossSelling;

use Nosto\NostoIntegration\Enums\CrossSellingSyncOptions;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingCollection;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\Product\SalesChannel\ProductCloseoutFilter;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CrossSellingBuilder
{
    public function __construct(
        private readonly EntityRepository $crossSellingRepository,
        private readonly ProductStreamBuilderInterface $productStreamBuilder,
        private readonly SalesChannelRepository $productRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly ConfigProvider $configProvider,
        private readonly ContainerInterface $container,
    ) {
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function build(string $productId, SalesChannelContext $context): array
    {
        $crossSellings = $this->loadCrossSellings($productId, $context);
        $result = [];
        foreach ($crossSellings as $crossSelling) {
            $result[$this->createKeyFromName($crossSelling->getTranslation('name'))] = [
                'productIds' => $this->useProductStream($crossSelling)
                    ? $this->loadByStream($crossSelling, $context, new Criteria())
                    : $this->loadByIds($crossSelling, $context, new Criteria()),
                'position' => $crossSelling->getPosition(),
                'sortBy' => $crossSelling->getSortBy(),
                'sortDirection' => $crossSelling->getSortDirection(),
                'limit' => $crossSelling->getLimit(),
                'name' => $crossSelling->getTranslation('name'),
            ];
        }
        return $result;
    }

    private function loadCrossSellings(string $productId, SalesChannelContext $context): ProductCrossSellingCollection
    {
        $syncConfig = $this->configProvider->getCrossSellingSyncOption(
            $context->getSalesChannelId(),
            $context->getLanguageId(),
        );
        if ($syncConfig === CrossSellingSyncOptions::NO_SYNC) {
            return new ProductCrossSellingCollection();
        }
        $criteria = new Criteria();
        $criteria
            ->addAssociation('assignedProducts')
            ->addFilter(new EqualsFilter('product.id', $productId))
            ->addSorting(new FieldSorting('position', FieldSorting::ASCENDING));
        if ($syncConfig === CrossSellingSyncOptions::ONLY_ACTIVE) {
            $criteria->addFilter(new EqualsFilter('active', 1));
        }
        return $this->crossSellingRepository
            ->search($criteria, $context->getContext())
            ->getEntities();
    }

    private function createKeyFromName(string $name): string
    {
        return strtolower(str_replace(' ', '-', $name));
    }

    private function useProductStream(ProductCrossSellingEntity $crossSelling): bool
    {
        return $crossSelling->getType() === ProductCrossSellingDefinition::TYPE_PRODUCT_STREAM
            && $crossSelling->getProductStreamId() !== null;
    }

    /**
     * @return string[]
     */
    protected function loadByStream(
        ProductCrossSellingEntity $crossSelling,
        SalesChannelContext $context,
        Criteria $criteria,
    ): array {
        /** @var string $productStreamId */
        $productStreamId = $crossSelling->getProductStreamId();

        $filters = $this->productStreamBuilder->buildFilters(
            $productStreamId,
            $context->getContext(),
        );

        $criteria->addFilter(...$filters)
            ->setOffset(0)
            ->addSorting($crossSelling->getSorting());

        $criteria = $this->handleAvailableStock($criteria, $context);

        return $this->productRepository->searchIds($criteria, $context)->getIds();
    }

    private function handleAvailableStock(Criteria $criteria, SalesChannelContext $context): Criteria
    {
        $salesChannelId = $context->getSalesChannel()->getId();
        $hide = $this->systemConfigService->get('core.listing.hideCloseoutProductsWhenOutOfStock', $salesChannelId);

        if (!$hide) {
            return $criteria;
        }

        $closeOutFactoryClass = 'Shopware\Core\Content\Product\SalesChannel\ProductCloseoutFilterFactory';
        $closeoutFilter = $this->container->has($closeOutFactoryClass)
            ? $this->container->get($closeOutFactoryClass)->create($context)
            : new ProductCloseoutFilter();

        $criteria->addFilter($closeoutFilter);

        return $criteria;
    }

    /**
     * @return string[]
     */
    protected function loadByIds(
        ProductCrossSellingEntity $crossSelling,
        SalesChannelContext $context,
        Criteria $criteria,
    ): array {
        if (!$crossSelling->getAssignedProducts()) {
            return [];
        }

        $crossSelling->getAssignedProducts()->getProductIds();

        $ids = array_values($crossSelling->getAssignedProducts()->getProductIds());

        $filter = new ProductAvailableFilter(
            $context->getSalesChannel()->getId(),
            ProductVisibilityDefinition::VISIBILITY_LINK,
        );

        if (!count($ids)) {
            return [];
        }

        $criteria->setIds($ids);
        $criteria->addFilter($filter);

        $criteria = $this->handleAvailableStock($criteria, $context);

        return $this->productRepository->searchIds($criteria, $context)->getIds();
    }
}
