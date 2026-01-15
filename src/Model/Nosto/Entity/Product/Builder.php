<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Entity\Product;

use Exception;
use Nosto\Helper\SerializationHelper;
use Nosto\Model\Product\Product as NostoProduct;
use Nosto\Model\Product\SkuCollection;
use Nosto\NostoException;
use Nosto\NostoIntegration\Enums\CategoryNamingOptions;
use Nosto\NostoIntegration\Enums\ProductIdentifierOptions;
use Nosto\NostoIntegration\Enums\RatingOptions;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\ProductHelper;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\Category\TreeBuilder;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\CrossSelling\CrossSellingBuilder;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\Event\NostoProductBuiltEvent;
use Nosto\Types\Product\ProductInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Cart\Price\NetPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Tag\TagCollection;
use Shopware\Core\System\Tag\TagEntity;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Nosto\NostoIntegration\Utils\NostoCriteriaFactory;

class Builder
{
    public const PRODUCT_ASSIGNMENT_TYPE = 'productAssignmentType';

    public const SHIPPING_FREE_ATTR_NAME = 'Shipping Free';

    public const PRODUCT_ID_KEY = 'productid';

    public const PRODUCT_NUMBER_KEY = 'productnumber';

    public const SHOW_CATEGORY = 'showCategory';

    public const SHOW_SEARCH = 'showSearch';

    public const SEARCH_KEYWORDS = 'searchKeywords';

    public function __construct(
        private readonly ConfigProvider $configProvider,
        private readonly ProductHelper $productHelper,
        private readonly NetPriceCalculator $calculator,
        private readonly CashRounding $priceRounding,
        private readonly SkuBuilder $skuBuilder,
        private readonly TreeBuilder $treeBuilder,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly CrossSellingBuilder $crossSellingBuilder,
        private readonly EntityRepository $tagRepository,
        private readonly SalesChannelRepository $categoryRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @var array<string, bool>
     */
    private array $loggingCache = [];

    /**
     * @var array<string, CategoryCollection>
     */
    private array $dynamicGroupCategoriesCache = [];

    /**
     * @var array<string, array<string, TagEntity>>
     */
    private array $tagCache = [];

    /**
     * @throws NostoException
     * @throws Exception
     */
    public function build(SalesChannelProductEntity $product, SalesChannelContext $context): NostoProduct
    {
        $nostoProduct = new NostoProduct();
        $channelId = $context->getSalesChannelId();
        $languageId = $context->getLanguageId();

        if ($product->getCategoriesRo() === null) {
            $product = $this->productHelper->reloadProduct($product->getId(), $context);
        }

        // Removes categories from products in which the product is included through manual addition,
        // but a dynamic group of products is currently selected there
        $this->makeActualProductCategories($product, $context);

        $url = $this->productHelper->getProductUrl($product, $context);
        if (!empty($url)) {
            $nostoProduct->setUrl($url);
        }

        $nostoProduct->setProductId(
            $this->configProvider->getProductIdentifier(
                $channelId,
                $languageId,
            ) === ProductIdentifierOptions::PRODUCT_NUMBER
                ? $product->getProductNumber()
                : $product->getId(),
        );
        $nostoProduct->addCustomField(self::PRODUCT_NUMBER_KEY, $product->getProductNumber());
        $nostoProduct->addCustomField(self::PRODUCT_ID_KEY, $product->getId());
        $name = $product->getTranslation('name');
        if (!empty($name)) {
            $nostoProduct->setName($name);
        }

        $nostoProduct->setPriceCurrencyCode($context->getCurrency()->getIsoCode());
        $stock = $this->productHelper->getProductStock($product, $context);
        $stockStatus = $stock > 0 ? ProductInterface::IN_STOCK : ProductInterface::OUT_OF_STOCK;

        if (!$product->getIsCloseout() && $stock < 1) {
            $stockStatus = ProductInterface::IN_STOCK;
        }

        $criteria = NostoCriteriaFactory::create();
        NostoCriteriaFactory::setTitle($criteria, 'product_sync.builder.loadCategorySeoUrls');
        $criteria->addAssociation('seoUrls');
        $criteria->addFilter(new EqualsAnyFilter('id', array_values($product->getCategoriesRo()->getIds())));
        $productCategoriesRo = $this->categoryRepository->search($criteria, $context)->getEntities();
        $product->setCategoriesRo($productCategoriesRo);

        $nostoProduct->setAvailability($stockStatus);

        if ($this->configProvider->getCategoryNamingOption(
            $channelId,
            $languageId,
        ) === CategoryNamingOptions::WITH_ID) {
            $nostoCategoryNames = $this->treeBuilder->fromCategoriesRoWithId($product->getCategoriesRo());
        } else {
            $nostoCategoryNames = $this->treeBuilder->fromCategoriesRo($product->getCategoriesRo());
        }

        if (!empty($nostoCategoryNames)) {
            $nostoProduct->setCategories($nostoCategoryNames);
        }

        $categoryIds = $this->getCategoryIds($product->getCategoriesRo());
        if (!empty($categoryIds)) {
            $nostoProduct->setCategoryIds($categoryIds);
        }

        $parentCategoryIds = $this->getParentCategoryIds($product->getCategoriesRo());
        if (!empty($parentCategoryIds)) {
            $nostoProduct->setParentCategoryIds($parentCategoryIds);
        }

        $ratingAvg = $product->getRatingAverage();
        if ($ratingAvg && $this->configProvider->getRatingReviews() === RatingOptions::SHOPWARE_RATINGS) {
            $nostoProduct->setRatingValue(round($ratingAvg, 1));
            $nostoProduct->setReviewCount($this->productHelper->getReviewsCount($product, $context));
        }

        if ($product->getPurchaseUnit() !== null) {
            $nostoProduct->setUnitPricingMeasure((string) $product->getPurchaseUnit());
        }

        if ($product->getReferenceUnit() !== null) {
            $nostoProduct->setUnitPricingBaseMeasure((string) $product->getReferenceUnit());
        }

        if ($product->getUnit()) {
            $unitName = $product->getUnit()->getTranslation('name') ?: $product->getUnit()->getName();
            if (!empty($unitName)) {
                $nostoProduct->setUnitPricingUnit($unitName);
            }
        }

        if ($product->getChildren()) {
            if ($this->configProvider->isEnabledVariations(
                $channelId,
                $languageId,
            ) && $product->getChildren()->count()) {
                $skuCollection = $this->preparingChildrenSkuCollection($product, $context);
                $nostoProduct->setSkus($skuCollection);
            }
        }

        if ($product->getShippingFree()) {
            $nostoProduct->addCustomField(self::SHIPPING_FREE_ATTR_NAME, 'true');
        }

        if (
            $this->configProvider->isEnabledProductProperties($channelId, $languageId) &&
            $product->getOptions() !== null
        ) {
            $options = $this->productHelper->preparePropertiesOrOptions($product->getOptions());
            foreach ($options as $name => $option) {
                $nostoProduct->addCustomField(
                    $name,
                    $option,
                );
            }
            $properties = $this->productHelper->preparePropertiesOrOptions($product->getProperties());
            foreach ($properties as $name => $property) {
                $nostoProduct->addCustomField(
                    $name,
                    $property,
                );
            }

            $this->initTags($product, $nostoProduct, $context);
            $selectedCustomFieldsCustomFields = $this->configProvider->getSelectedCustomFields($channelId, $languageId);

            foreach ($product->getTranslation('customFields') as $fieldName => $fieldOriginalValue) {
                // All non-scalar value should be serialized
                $fieldValue = $fieldOriginalValue === null || is_scalar($fieldOriginalValue) ?
                    $fieldOriginalValue : SerializationHelper::serialize($fieldOriginalValue);

                if (in_array($fieldName, $selectedCustomFieldsCustomFields) && $fieldValue !== null) {
                    $nostoProduct->addCustomField(mb_strtolower($fieldName), $fieldValue);
                }
            }
        }

        if ($product->getCover()) {
            $nostoProduct->setImageUrl($product->getCover()->getMedia()->getUrl());
            $nostoProduct->setThumbUrl($product->getCover()->getMedia()->getUrl());
        } else {
            $placeholderImageUrl = $this->productHelper->getFallbackImageUrl($context);
            $nostoProduct->setImageUrl($placeholderImageUrl);
            $nostoProduct->setThumbUrl($placeholderImageUrl);
        }

        if ($this->configProvider->isEnabledAlternateImages($channelId, $languageId)) {
            $alternateMedia = $product->getMedia();
            $alternateMedia->sort(
                fn (ProductMediaEntity $a, ProductMediaEntity $b) => $a->getPosition() <=> $b->getPosition(),
            );

            $alternateMediaUrls = array_values(
                array_filter(
                    $alternateMedia->map(static fn (ProductMediaEntity $media) => $media->getMedia()?->getUrl()),
                ),
            );

            $nostoProduct->setAlternateImageUrls(array_values($alternateMediaUrls));
        }

        if ($manufacturer = $product->getManufacturer()) {
            $nostoProduct->setBrand($manufacturer->getTranslation('name'));
            if ($brandMediaUrl = $manufacturer->getMedia()?->getUrl()) {
                $nostoProduct->addCustomField('brand-image-url', $brandMediaUrl);
            }
        }

        if ($description = $product->getTranslation('description')) {
            $nostoProduct->setDescription($description);
        }

        if ($this->configProvider->isEnabledInventoryLevels($channelId, $languageId)) {
            $nostoProduct->setInventoryLevel($stock);
        }

        if ($this->configProvider->isEnabledProductPublishedDateTagging($channelId, $languageId)) {
            $nostoProduct->setDatePublished($product->getCreatedAt()->format('Y-m-d'));
        }

        $this->setPrices($nostoProduct, $product, $context);

        $crossSellings = $this->crossSellingBuilder->build($product->getId(), $context);

        if (!empty($crossSellings)) {
            $nostoProduct->addCustomField('cross-sellings', json_encode($crossSellings));
        }

        if ($this->configProvider->isEnabledProductLabellingSync($channelId, $languageId)) {
            $nostoProduct->addCustomField(
                'product-labels_release-date',
                $product->getReleaseDate()?->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            );
            $nostoProduct->addCustomField('product-labels_mfg-part-number', $product->getManufacturerNumber());
            $nostoProduct->addCustomField('product-labels_gtin-ean', $product->getEan());
        }

        if (method_exists($product, 'getVariantListingConfig') && $product->getVariantListingConfig()) {
            $nostoProduct->addCustomField('variant-listing-config', json_encode($product->getVariantListingConfig()));
        }

        foreach ($product->getVisibilities() as $visibility) {
            if ($channelId === $visibility->getSalesChannelId()) {
                switch ($visibility->getVisibility()) {
                    case ProductVisibilityDefinition::VISIBILITY_ALL:
                        $showSearch = 'true';
                        $showCategory = 'true';
                        break;
                    case ProductVisibilityDefinition::VISIBILITY_SEARCH:
                        $showSearch = 'true';
                        $showCategory = 'false';
                        break;
                    default:
                        $showSearch = 'false';
                        $showCategory = 'false';
                }
                $nostoProduct->addCustomField(self::SHOW_CATEGORY, $showCategory);
                $nostoProduct->addCustomField(self::SHOW_SEARCH, $showSearch);
                break;
            }
        }

        if ($keywords = $product->getCustomSearchKeywords()) {
            $nostoProduct->addCustomField(self::SEARCH_KEYWORDS, implode(', ', $keywords));
        }

        $this->eventDispatcher->dispatch(new NostoProductBuiltEvent($product, $nostoProduct, $context));

        return $nostoProduct;
    }

    private function setPrices(
        NostoProduct $nostoProdcut,
        SalesChannelProductEntity $product,
        SalesChannelContext $context,
    ): void {
        $productPrice = $product->getCalculatedPrices()->first() ?: $product->getCalculatedPrice();
        if (!($productPrice instanceof CalculatedPrice)) {
            return;
        }

        $listPrice = $productPrice->getListPrice() ?
            $productPrice->getListPrice()->getPrice() :
            $productPrice->getUnitPrice();

        $unitPrice = $productPrice->getUnitPrice();
        $isGross = empty($context->getCurrentCustomerGroup()) || $context->getCurrentCustomerGroup()->getDisplayGross();

        if ($product->getPurchasePrices()) {
            $supplierCost = $product->getPurchasePrices()->first() ?: $product->getPurchasePrices();
            $supplierCostPrice = $supplierCost->getGross();

            if (!$isGross) {
                $priceSupplierCost = $this->calculator->calculate(
                    new QuantityPriceDefinition($supplierCostPrice, $productPrice->getTaxRules(), 1),
                    $context->getItemRounding(),
                );
                foreach ($priceSupplierCost->getCalculatedTaxes()->getElements() as $tax) {
                    $supplierCostPrice += ($tax->getTax() + $tax->getPrice());
                }
            }

            $nostoProdcut->setSupplierCost(
                $this->priceRounding->cashRound($supplierCostPrice, $context->getItemRounding()),
            );
        }

        if (!$isGross) {
            $price = $this->calculator->calculate(
                new QuantityPriceDefinition($unitPrice, $productPrice->getTaxRules(), 1),
                $context->getItemRounding(),
            );

            $priceList = $this->calculator->calculate(
                new QuantityPriceDefinition($listPrice, $productPrice->getTaxRules(), 1),
                $context->getItemRounding(),
            );
            $unitPrice = $listPrice = 0;

            foreach ($price->getCalculatedTaxes()->getElements() as $tax) {
                $unitPrice += ($tax->getTax() + $tax->getPrice());
            }

            foreach ($priceList->getCalculatedTaxes()->getElements() as $tax) {
                $listPrice += ($tax->getTax() + $tax->getPrice());
            }
        }

        $nostoProdcut->setPrice($this->priceRounding->cashRound($unitPrice, $context->getItemRounding()));
        $nostoProdcut->setListPrice($this->priceRounding->cashRound($listPrice, $context->getItemRounding()));
    }

    private function initTags(
        SalesChannelProductEntity $productEntity,
        NostoProduct $nostoProduct,
        SalesChannelContext $context,
    ): void {
        $shouldLog = $this->shouldLogExtra($context);
        $startedAt = $shouldLog ? microtime(true) : null;
        $channelId = $context->getSalesChannelId();
        $languageId = $context->getLanguageId();

        $tagFieldKeys = [
            1 => $this->configProvider->getTagFieldKey(1, $channelId, $languageId),
            2 => $this->configProvider->getTagFieldKey(2, $channelId, $languageId),
            3 => $this->configProvider->getTagFieldKey(3, $channelId, $languageId),
        ];

        $tagIdsToLoad = array_values(array_unique(array_merge(...array_values($tagFieldKeys))));
        $productTagIds = $productEntity->getTagIds() ?? [];
        if (!empty($productTagIds)) {
            $tagIdsToLoad = array_values(array_intersect($tagIdsToLoad, $productTagIds));
        } else {
            $tagIdsToLoad = [];
        }

        $tags = $this->loadTagsByIds($tagIdsToLoad, $context->getContext());

        $nostoProduct->setTag1($this->getTagValues(
            $productEntity,
            $tagFieldKeys[1],
            $tags,
        ));
        $nostoProduct->setTag2($this->getTagValues(
            $productEntity,
            $tagFieldKeys[2],
            $tags,
        ));
        $nostoProduct->setTag3($this->getTagValues(
            $productEntity,
            $tagFieldKeys[3],
            $tags,
        ));

        if ($shouldLog && $startedAt !== null) {
            $this->logDuration(
                $context,
                'product_sync.builder.initTags',
                $startedAt,
                [
                    'loaded_tags' => $tags->count(),
                    'product_id' => $productEntity->getId(),
                ],
            );
        }
    }

    private function getTagValues(ProductEntity $productEntity, array $tagIds, TagCollection $allTags): array
    {
        $result = [];
        foreach ($tagIds as $tagId) {
            if (
                $allTags->has($tagId) &&
                !empty($productEntity->getTagIds()) &&
                in_array($tagId, $productEntity->getTagIds())
            ) {
                $result[] = $allTags->get($tagId)->getName();
            }
        }
        return $result;
    }

    private function loadTagsByIds(array $tagIds, Context $context): TagCollection
    {
        if ($tagIds === []) {
            return new TagCollection();
        }

        $criteria = NostoCriteriaFactory::create();
        NostoCriteriaFactory::setTitle($criteria, 'product_sync.builder.loadTagsByIds');
        $criteria->addFilter(new EqualsAnyFilter('id', array_values($tagIds)));

        return $this->tagRepository->search($criteria, $context)->getEntities();
    }

    private function getCategoryIds(CategoryCollection $categoriesRo): array
    {
        return array_values(
            array_map(function (CategoryEntity $category) {
                return $category->getId();
            }, $categoriesRo->getElements()),
        );
    }

    /**
     * @return string[]
     */
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

    private function makeActualProductCategories(
        ?SalesChannelProductEntity $product,
        SalesChannelContext $context,
    ): void {
        $shouldLog = $this->shouldLogExtra($context);
        $startedAt = $shouldLog ? microtime(true) : null;
        $categories = $this->getCategoriesWithDynamicProductGroups($context);
        $productCategoryRoIds = $product->getCategoriesRo()->getIds();
        $dynamicGroupCategoryIds = $dynamicGroupCategoryPaths = [];

        if ($categories->count() > 0) {
            foreach ($categories as $category) {
                if (!$category->getProductStreamId()) {
                    continue;
                }

                $dynamicGroupCategoryIds[] = $category->getId();
                $dynamicGroupCategoryPaths[$category->getProductStreamId()][] =
                    $category->getPath() . $category->getId();
            }
        }

        try {
            // Clearing a product from categories associated with a dynamic group
            if (!empty($productCategoryRoIds) && !empty($dynamicGroupCategoryIds)) {
                foreach ($productCategoryRoIds as $productCategoryRoId) {
                    if (in_array($productCategoryRoId, $dynamicGroupCategoryIds)) {
                        $product->getCategoriesRo()->remove($productCategoryRoId);
                    }
                }
            }

            // Preparing categories for dynamic group products
            $this->addCategoriesByDynamicGroupsAssigned($product, $context, $dynamicGroupCategoryPaths);
        } catch (Exception $e) {
            throw new Exception(
                'Cannot clear a product from categories associated with a dynamic group: ' . $e->getMessage(),
            );
        } finally {
            if ($shouldLog && $startedAt !== null) {
                $this->logDuration(
                    $context,
                    'product_sync.builder.makeActualProductCategories',
                    $startedAt,
                    [
                        'product_id' => $product?->getId(),
                        'initial_category_count' => count($productCategoryRoIds),
                        'dynamic_category_count' => count($dynamicGroupCategoryIds),
                    ],
                );
            }
        }
    }

    private function getCategoriesWithDynamicProductGroups(SalesChannelContext $context): CategoryCollection
    {
        $criteria = NostoCriteriaFactory::create();
        NostoCriteriaFactory::setTitle($criteria, 'product_sync.builder.dynamicGroupCategories');
        $criteria->addFilter(
            new EqualsFilter(
                self::PRODUCT_ASSIGNMENT_TYPE,
                CategoryDefinition::PRODUCT_ASSIGNMENT_TYPE_PRODUCT_STREAM,
            ),
        );

        return $this->categoryRepository->search($criteria, $context)->getEntities();
    }

    private function addCategoriesByDynamicGroupsAssigned(
        SalesChannelProductEntity $product,
        SalesChannelContext $context,
        array $dynamicGroupCategoryPaths,
    ): void {
        $productCategoriesRo = $product->getCategoriesRo();

        // Preparing categories for dynamic group products
        if (!empty($dynamicGroupCategoryPaths) && $product->getStreamIds()) {
            $allProductCategoryPaths = '';

            foreach ($product->getStreamIds() as $streamId) {
                if (array_key_exists($streamId, $dynamicGroupCategoryPaths)) {
                    $allProductCategoryPaths .= implode('|', $dynamicGroupCategoryPaths[$streamId]);
                }
            }

            if ($productCategoriesRo && $productCategoriesRo->count()) {
                foreach ($productCategoriesRo as $category) {
                    $allProductCategoryPaths .= '|' . $category->getId();
                }
            }

            $productCategoriesCollection = $this->getCategoriesTreeCollection($allProductCategoryPaths, $context);

            if ($productCategoriesCollection->count() > 0) {
                $product->setCategoriesRo($productCategoriesCollection);
            }
        }
    }

    private function getCategoriesTreeCollection($allProductCategoryPaths, $context): CategoryCollection
    {
        $categoriesPaths = array_filter(array_unique(explode('|', $allProductCategoryPaths)));

        $criteria = NostoCriteriaFactory::create();
        NostoCriteriaFactory::setTitle($criteria, 'product_sync.builder.categoriesTreeCollection');
        $criteria->addFilter(
            new EqualsAnyFilter('id', $categoriesPaths),
        );

        return $this->categoryRepository->search($criteria, $context)->getEntities();
    }

    private function shouldLogExtra(SalesChannelContext $context): bool
    {
        $cacheKey = sprintf('%s-%s', $context->getSalesChannelId(), $context->getLanguageId());
        if (!array_key_exists($cacheKey, $this->loggingCache)) {
            $this->loggingCache[$cacheKey] = $this->configProvider->isEnabledProductSyncExtraLogging(
                $context->getSalesChannelId(),
                $context->getLanguageId(),
            );
        }

        return $this->loggingCache[$cacheKey];
    }

    private function logDuration(
        SalesChannelContext $context,
        string $message,
        float $startedAt,
        array $additionalContext = [],
    ): void {
        $durationMs = (microtime(true) - $startedAt) * 1000;
        $this->logger->info($message, array_merge(
            $additionalContext,
            [
                'duration_ms' => round($durationMs, 2),
                'sales_channel_id' => $context->getSalesChannelId(),
                'language_id' => $context->getLanguageId(),
            ],
        ));
    }

    private function preparingChildrenSkuCollection(
        SalesChannelProductEntity $product,
        SalesChannelContext $context,
    ): SkuCollection {
        $shouldLog = $this->shouldLogExtra($context);
        $startedAt = $shouldLog ? microtime(true) : null;
        $skuCollection = new SkuCollection();

        if ($product->getChildren()->count()) {
            $salesChannelId = $context->getSalesChannelId();
            $languageId = $context->getLanguageId();

            $criteria = NostoCriteriaFactory::create();
            NostoCriteriaFactory::setTitle($criteria, 'product_sync.builder.childrenSku');
            $criteria->addAssociation('media');
            $criteria->addAssociation('cover');
            $criteria->addAssociation('options.group');
            $criteria->addAssociation('properties.group');
            $criteria->addAssociation('manufacturer');
            $criteria->addAssociation('manufacturer.media');
            $criteria->addAssociation('categoriesRo');
            $criteria->addAssociation('visibilities');
            $criteria->addFilter(new EqualsAnyFilter('id', $product->getChildren()->getIds()));

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

            $iterator = $this->productHelper->createRepositoryIterator($criteria, $context->getContext());

            while (($children = $iterator->fetch()) !== null) {
                $shopwareProducts = $this->productHelper->getShopwareProducts($children->getIds(), $context);
                foreach ($children as $variationProduct) {
                    $shopwareProduct = $shopwareProducts->get($variationProduct->getId());
                    $skuCollection->append($this->skuBuilder->build($shopwareProduct ?: $variationProduct, $context));
                }
            }
        }

        if ($shouldLog && $startedAt !== null) {
            $this->logDuration(
                $context,
                'product_sync.builder.preparingChildrenSkuCollection',
                $startedAt,
                [
                    'product_id' => $product->getId(),
                    'children_count' => $product->getChildren()->count(),
                ],
            );
        }

        return $skuCollection;
    }
}
