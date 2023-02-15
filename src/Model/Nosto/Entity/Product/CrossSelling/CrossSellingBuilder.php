<?php

namespace Od\NostoIntegration\Model\Nosto\Entity\Product\CrossSelling;

use Od\NostoIntegration\Model\ConfigProvider;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingCollection;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\SalesChannel\AbstractProductCloseoutFilterFactory;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use function count;


class CrossSellingBuilder implements CrossSellingBuilderInterface
{
    private EntityRepositoryInterface $crossSellingRepository;
    private ProductStreamBuilderInterface $productStreamBuilder;
    private SalesChannelRepositoryInterface $productRepository;
    private SystemConfigService $systemConfigService;
    private AbstractProductCloseoutFilterFactory $productCloseoutFilterFactory;
    private ConfigProvider $configProvider;

    public function __construct(
        EntityRepositoryInterface            $crossSellingRepository,
        ProductStreamBuilderInterface        $productStreamBuilder,
        SalesChannelRepositoryInterface      $productRepository,
        SystemConfigService                  $systemConfigService,
        AbstractProductCloseoutFilterFactory $productCloseoutFilterFactory,
        ConfigProvider $configProvider
    )
    {
        $this->crossSellingRepository = $crossSellingRepository;
        $this->productStreamBuilder = $productStreamBuilder;
        $this->productRepository = $productRepository;
        $this->systemConfigService = $systemConfigService;
        $this->productCloseoutFilterFactory = $productCloseoutFilterFactory;
        $this->configProvider = $configProvider;
    }

    public function build(string $productId, SalesChannelContext $context): array
    {
        $crossSellings = $this->loadCrossSellings($productId, $context);
        $result = [];
        foreach ($crossSellings as $crossSelling) {
            $result[$this->createKeyFromName($crossSelling->getName())] = $this->useProductStream($crossSelling) ? $this->loadByStream($crossSelling, $context, new Criteria()) : $this->loadByIds($crossSelling, $context, new Criteria());
        }
        return $result;
    }

    private function loadCrossSellings(string $productId, SalesChannelContext $context): ProductCrossSellingCollection
    {
        $syncConfig = $this->configProvider->getCrossSellingSyncOption($context->getSalesChannelId());
        if ($syncConfig === 'no-sync') {
            return new ProductCrossSellingCollection();
        }
        $criteria = new Criteria();
        $criteria
            ->addAssociation('assignedProducts')
            ->addFilter(new EqualsFilter('product.id', $productId))
            ->addSorting(new FieldSorting('position', FieldSorting::ASCENDING));
        if ($syncConfig === 'only-active-sync') {
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

    protected function loadByStream(ProductCrossSellingEntity $crossSelling, SalesChannelContext $context, Criteria $criteria): array
    {
        /** @var string $productStreamId */
        $productStreamId = $crossSelling->getProductStreamId();

        $filters = $this->productStreamBuilder->buildFilters(
            $productStreamId,
            $context->getContext()
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

        $closeoutFilter = $this->productCloseoutFilterFactory->create($context);
        $criteria->addFilter($closeoutFilter);

        return $criteria;
    }

    protected function loadByIds(ProductCrossSellingEntity $crossSelling, SalesChannelContext $context, Criteria $criteria): array
    {
        if (!$crossSelling->getAssignedProducts()) {
            return [];
        }

        $crossSelling->getAssignedProducts()->getProductIds();

        $ids = array_values($crossSelling->getAssignedProducts()->getProductIds());

        $filter = new ProductAvailableFilter(
            $context->getSalesChannel()->getId(),
            ProductVisibilityDefinition::VISIBILITY_LINK
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
