<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Entity\Product;

use Nosto\Model\Product\Product as NostoProduct;
use Nosto\Model\Product\Sku as NostoSku;
use Nosto\Model\Product\SkuCollection;
use Nosto\NostoIntegration\Enums\CategoryNamingOptions;
use Nosto\NostoIntegration\Enums\ProductIdentifierOptions;
use Nosto\NostoIntegration\Enums\RatingOptions;
use Nosto\NostoIntegration\Enums\StockFieldOptions;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\ProductHelper;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\Category\TreeBuilder;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\CrossSelling\CrossSellingBuilder;
use Nosto\NostoIntegration\Utils\NostoCriteriaFactory;
use Nosto\Types\Product\ProductInterface;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Cart\Price\NetPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Tag\TagCollection;
use Shopware\Core\System\Tag\TagEntity;

class PartialBuilder
{
    /**
     * @var array<string, CategoryCollection>
     */
    private array $dynamicGroupCategoriesCache = [];

    /**
     * @var array<string, array<string, TagEntity>>
     */
    private array $tagCache = [];

    /**
     * @var array<string, bool>
     */
    private array $loggingCache = [];

    public function __construct(
        private readonly ConfigProvider $configProvider,
        private readonly ProductHelper $productHelper,
        private readonly NetPriceCalculator $calculator,
        private readonly CashRounding $priceRounding,
        private readonly SkuBuilder $skuBuilder,
        private readonly TreeBuilder $treeBuilder,
        private readonly CrossSellingBuilder $crossSellingBuilder,
        private readonly \Shopware\Core\Framework\DataAbstractionLayer\EntityRepository $tagRepository,
        private readonly SalesChannelRepository $categoryRepository,
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

    private function buildSku(object $product, SalesChannelContext $context): NostoSku
    {
        if ($product instanceof \Shopware\Core\Content\Product\ProductEntity) {
            return $this->skuBuilder->build($product, $context);
        }

        $productId = $this->getValue($product, 'id');
        if ($productId !== null) {
            $loadedProduct = $this->productHelper->reloadProduct((string) $productId, $context);
            if ($loadedProduct instanceof \Shopware\Core\Content\Product\ProductEntity) {
                return $this->skuBuilder->build($loadedProduct, $context);
            }
        }

        return new NostoSku();
    }

    private function setPrices(NostoProduct $nostoProduct, object $product, SalesChannelContext $context): void
    {
        $price = $this->getCurrencyPrice($product, $context);
        if ($price === null) {
            return;
        }

        if ($price instanceof CalculatedPrice) {
            $listPrice = $price->getListPrice()?->getPrice() ?? $price->getUnitPrice();
            $unitPrice = $price->getUnitPrice();

            $isGross = empty($context->getCurrentCustomerGroup()) || $context->getCurrentCustomerGroup()->getDisplayGross();
            if (!$isGross) {
                $calc = $this->calculator->calculate(
                    new QuantityPriceDefinition($unitPrice, $price->getTaxRules(), 1),
                    $context->getItemRounding(),
                );
                $calcList = $this->calculator->calculate(
                    new QuantityPriceDefinition($listPrice, $price->getTaxRules(), 1),
                    $context->getItemRounding(),
                );
                $unitPrice = 0;
                $listPrice = 0;
                foreach ($calc->getCalculatedTaxes()->getElements() as $tax) {
                    $unitPrice += ($tax->getTax() + $tax->getPrice());
                }
                foreach ($calcList->getCalculatedTaxes()->getElements() as $tax) {
                    $listPrice += ($tax->getTax() + $tax->getPrice());
                }
            }

            $nostoProduct->setPrice($this->priceRounding->cashRound($unitPrice, $context->getItemRounding()));
            $nostoProduct->setListPrice($this->priceRounding->cashRound($listPrice, $context->getItemRounding()));
            return;
        }

        if ($price instanceof \Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price) {
            $nostoProduct->setPrice($price->getGross());
            if ($price->getListPrice() !== null) {
                $nostoProduct->setListPrice($price->getListPrice()->getGross());
            }
        }
    }

    private function setSkuPrices(NostoSku $sku, object $product, SalesChannelContext $context): void
    {
        $price = $this->getCurrencyPrice($product, $context);
        if ($price === null) {
            return;
        }

        if ($price instanceof CalculatedPrice) {
            $sku->setPrice($price->getUnitPrice());
            if ($price->getListPrice() !== null) {
                $sku->setListPrice($price->getListPrice()->getPrice());
            }
            return;
        }

        if ($price instanceof \Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price) {
            $sku->setPrice($price->getGross());
            if ($price->getListPrice() !== null) {
                $sku->setListPrice($price->getListPrice()->getGross());
            }
        }
    }

    private function getCurrencyPrice(object $product, SalesChannelContext $context): CalculatedPrice|\Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price|null
    {
        $price = $this->getValue($product, 'calculatedPrice');
        if ($price instanceof CalculatedPrice) {
            return $price;
        }

        $rawPrices = $this->getValue($product, 'price');
        if ($rawPrices instanceof PriceCollection) {
            $currencyPrice = $rawPrices->getCurrencyPrice($context->getCurrencyId());
            if ($currencyPrice) {
                return $currencyPrice;
            }
        }

        return null;
    }

    private function applyCategories(NostoProduct $nostoProduct, object $product, SalesChannelContext $context): void
    {
        $categories = $this->getValue($product, 'categoriesRo');
        if (!$categories instanceof EntityCollection) {
            return;
        }

        $categoryIds = $categories->getIds();
        if ($categoryIds === []) {
            return;
        }

        $criteria = NostoCriteriaFactory::create('product_sync.builder.loadCategorySeoUrls.partial');
        $criteria->addAssociation('seoUrls');
        $criteria->addFilter(new \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter('id', array_values($categoryIds)));
        $productCategoriesRo = $this->categoryRepository->search($criteria, $context)->getEntities();

        $categoryNaming = $this->configProvider->getCategoryNamingOption(
            $context->getSalesChannelId(),
            $context->getLanguageId(),
        );
        $categoryNames = $categoryNaming === CategoryNamingOptions::WITH_ID
            ? $this->treeBuilder->fromCategoriesRoWithId($productCategoriesRo)
            : $this->treeBuilder->fromCategoriesRo($productCategoriesRo);

        if (!empty($categoryNames)) {
            $nostoProduct->setCategories($categoryNames);
        }

        $nostoProduct->setCategoryIds($this->getCategoryIds($productCategoriesRo));
        $nostoProduct->setParentCategoryIds($this->getParentCategoryIds($productCategoriesRo));
    }

    private function initTags(object $productEntity, NostoProduct $nostoProduct, SalesChannelContext $context): void
    {
        $channelId = $context->getSalesChannelId();
        $languageId = $context->getLanguageId();

        $tagFieldKeys = [
            1 => $this->configProvider->getTagFieldKey(1, $channelId, $languageId),
            2 => $this->configProvider->getTagFieldKey(2, $channelId, $languageId),
            3 => $this->configProvider->getTagFieldKey(3, $channelId, $languageId),
        ];

        $tagIdsToLoad = array_values(array_unique(array_merge(...array_values($tagFieldKeys))));
        $productTagIds = $this->getValue($productEntity, 'tagIds') ?? [];
        if (!empty($productTagIds)) {
            $tagIdsToLoad = array_values(array_intersect($tagIdsToLoad, $productTagIds));
        } else {
            $tagIdsToLoad = [];
        }

        $tags = $this->loadTagsByIds($tagIdsToLoad, $context);

        $nostoProduct->setTag1($this->getTagValues($productEntity, $tagFieldKeys[1], $tags));
        $nostoProduct->setTag2($this->getTagValues($productEntity, $tagFieldKeys[2], $tags));
        $nostoProduct->setTag3($this->getTagValues($productEntity, $tagFieldKeys[3], $tags));
    }

    private function getTagValues(object $productEntity, array $tagIds, TagCollection $allTags): array
    {
        $result = [];
        $productTagIds = $this->getValue($productEntity, 'tagIds') ?? [];
        foreach ($tagIds as $tagId) {
            if ($allTags->has($tagId) && !empty($productTagIds) && in_array($tagId, $productTagIds, true)) {
                $result[] = $allTags->get($tagId)->getName();
            }
        }
        return $result;
    }

    private function loadTagsByIds(array $tagIds, SalesChannelContext $context): TagCollection
    {
        if ($tagIds === []) {
            return new TagCollection();
        }

        $cacheKey = $this->buildCacheKey($context);
        if (!isset($this->tagCache[$cacheKey])) {
            $this->tagCache[$cacheKey] = [];
        }

        $cachedTags = &$this->tagCache[$cacheKey];
        $missingIds = array_diff($tagIds, array_keys($cachedTags));

        if ($missingIds !== []) {
            $criteria = NostoCriteriaFactory::create('product_sync.builder.loadTagsByIds.partial');
            $criteria->addFilter(new \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter('id', array_values($missingIds)));
            $fetched = $this->tagRepository->search($criteria, $context->getContext())->getEntities();

            foreach ($fetched as $tag) {
                $cachedTags[$tag->getId()] = $tag;
            }
        }

        $collection = new TagCollection();
        foreach ($tagIds as $tagId) {
            if (isset($cachedTags[$tagId])) {
                $collection->add($cachedTags[$tagId]);
            }
        }

        return $collection;
    }

    private function getCategoryIds(CategoryCollection $categoriesRo): array
    {
        return array_values(
            array_map(static fn (CategoryEntity $category) => $category->getId(), $categoriesRo->getElements()),
        );
    }

    private function getParentCategoryIds(CategoryCollection $categoriesRo): array
    {
        $parentIds = [];
        foreach ($categoriesRo as $category) {
            if (!$category->getPath()) {
                continue;
            }

            $parentIds = array_merge($parentIds, explode('|', $category->getPath()));
        }

        $parentIds = array_filter($parentIds, static fn (string $id) => !empty($id));

        return array_values(array_unique($parentIds));
    }

    private function getStock(object $product, SalesChannelContext $context): int
    {
        $stock = (int) ($this->getValue($product, 'stock') ?? 0);
        $availableStock = (int) ($this->getValue($product, 'availableStock') ?? 0);
        return $this->configProvider->getStockField(
            $context->getSalesChannelId(),
            $context->getLanguageId(),
        ) === StockFieldOptions::ACTUAL_STOCK ? $stock : $availableStock;
    }

    private function getBool(object $product, string $field): bool
    {
        return (bool) ($this->getValue($product, $field) ?? false);
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

    private function getTranslation(object $product, string $field)
    {
        if (method_exists($product, 'getTranslation')) {
            return $product->getTranslation($field);
        }
        $translated = $this->getValue($product, 'translated');
        if (is_array($translated)) {
            return $translated[$field] ?? null;
        }
        return null;
    }

    private function extractMediaUrl($entity): ?string
    {
        if ($entity === null) {
            return null;
        }
        if (method_exists($entity, 'getMedia') && $entity->getMedia()) {
            return $entity->getMedia()->getUrl();
        }
        if (method_exists($entity, 'getUrl')) {
            return $entity->getUrl();
        }
        if (method_exists($entity, 'get')) {
            $media = $entity->get('media');
            if ($media && method_exists($media, 'getUrl')) {
                return $media->getUrl();
            }
        }
        return null;
    }

    private function buildCacheKey(SalesChannelContext $context): string
    {
        return sprintf('%s-%s', $context->getSalesChannelId(), $context->getLanguageId());
    }

    private function toSalesChannelEntity(object $product): SalesChannelProductEntity
    {
        if ($product instanceof SalesChannelProductEntity) {
            return $product;
        }
        $entity = new SalesChannelProductEntity();
        if (method_exists($entity, 'setId')) {
            $entity->setId((string) $this->getValue($product, 'id'));
        }
        if (method_exists($entity, 'setParentId')) {
            $entity->setParentId($this->getValue($product, 'parentId'));
        }
        return $entity;
    }
}
