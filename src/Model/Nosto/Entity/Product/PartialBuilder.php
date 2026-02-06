<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Entity\Product;

use Nosto\Model\Product\Product as NostoProduct;
use Nosto\Model\Product\SkuCollection;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\ProductHelper;
use Nosto\NostoIntegration\Utils\NostoCriteriaFactory;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PartialBuilder
{
    public function __construct(
        private readonly ConfigProvider $configProvider,
        private readonly ProductHelper $productHelper,
        private readonly SkuBuilder $skuBuilder,
        private readonly Builder $builder,
    ) {
    }

    public function build(object $product, SalesChannelContext $context): NostoProduct
    {
        $productId = $this->getValue($product, 'id');
        if ($productId === null) {
            return new NostoProduct();
        }

        $loadedProduct = $this->productHelper->reloadProduct((string) $productId, $context);
        if ($loadedProduct instanceof SalesChannelProductEntity) {
            $nostoProduct = $this->builder->build($loadedProduct, $context);
            $this->maybeOverrideSkus($nostoProduct, $product, $context);
            return $nostoProduct;
        }

        if ($product instanceof SalesChannelProductEntity) {
            $nostoProduct = $this->builder->build($product, $context);
            $this->maybeOverrideSkus($nostoProduct, $product, $context);
            return $nostoProduct;
        }

        return new NostoProduct();
    }

    private function maybeOverrideSkus(NostoProduct $nostoProduct, object $product, SalesChannelContext $context): void
    {
        if ($nostoProduct->getSkus() !== null && $nostoProduct->getSkus()->count()) {
            return;
        }

        $skuCollection = $this->preparingChildrenSkuCollection($product, $context);
        if ($skuCollection !== null && $skuCollection->count()) {
            $nostoProduct->setSkus($skuCollection);
        }
    }

    private function preparingChildrenSkuCollection(object $product, SalesChannelContext $context): ?SkuCollection
    {
        $children = $this->getValue($product, 'children');
        if (!$children instanceof EntityCollection || !$children->count()) {
            return null;
        }

        $salesChannelId = $context->getSalesChannelId();
        $languageId = $context->getLanguageId();

        $criteria = NostoCriteriaFactory::create('product_sync.builder.childrenSku.partial');
        $criteria->addAssociation('media');
        $criteria->addAssociation('cover');
        $criteria->addAssociation('options.group');
        $criteria->addAssociation('properties.group');
        $criteria->addAssociation('manufacturer');
        $criteria->addAssociation('manufacturer.media');
        $criteria->addAssociation('categoriesRo');
        $criteria->addAssociation('visibilities');
        $criteria->addFilter(new EqualsAnyFilter('id', $children->getIds()));

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

        $skuCollection = new SkuCollection();
        $iterator = $this->productHelper->createRepositoryIterator($criteria, $context->getContext());
        while (($fetched = $iterator->fetch()) !== null) {
            $shopwareProducts = $this->productHelper->getShopwareProducts($fetched->getIds(), $context);
            foreach ($fetched as $variationProduct) {
                $shopwareProduct = $shopwareProducts->get($variationProduct->getId());
                $skuCollection->append($this->skuBuilder->build($shopwareProduct ?: $variationProduct, $context));
            }
        }

        return $skuCollection;
    }

    private function getValue(object $product, string $field)
    {
        if (method_exists($product, 'get')) {
            return $product->get($field);
        }
        if (property_exists($product, $field)) {
            return $product->$field;
        }
        return null;
    }
}
