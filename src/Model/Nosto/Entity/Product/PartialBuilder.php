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
        private readonly TreeBuilder $treeBuilder,
        private readonly CrossSellingBuilder $crossSellingBuilder,
        private readonly \Shopware\Core\Framework\DataAbstractionLayer\EntityRepository $tagRepository,
        private readonly SalesChannelRepository $categoryRepository,
    ) {
    }

    public function build(object $product, SalesChannelContext $context): NostoProduct
    {
        $nostoProduct = new NostoProduct();
        $channelId = $context->getSalesChannelId();
        $languageId = $context->getLanguageId();

        $productId = $this->getValue($product, 'id');
        $productNumber = $this->getValue($product, 'productNumber');

        if ($productId === null) {
            return $nostoProduct;
        }

        $url = $this->productHelper->getProductUrlById($productId, $context);
        if (!empty($url)) {
            $nostoProduct->setUrl($url);
        }

        $nostoProduct->setProductId(
            $this->configProvider->getProductIdentifier($channelId, $languageId) === ProductIdentifierOptions::PRODUCT_NUMBER
                ? $productNumber
                : $productId,
        );
        $nostoProduct->addCustomField(Builder::PRODUCT_NUMBER_KEY, $productNumber);
        $nostoProduct->addCustomField(Builder::PRODUCT_ID_KEY, $productId);

        $name = $this->getTranslation($product, 'name');
        if (!empty($name)) {
            $nostoProduct->setName($name);
        }

        $nostoProduct->setPriceCurrencyCode($context->getCurrency()->getIsoCode());

        $stock = $this->getStock($product, $context);
        $stockStatus = $stock > 0 ? ProductInterface::IN_STOCK : ProductInterface::OUT_OF_STOCK;
        if (!$this->getBool($product, 'isCloseout') && $stock < 1) {
            $stockStatus = ProductInterface::IN_STOCK;
        }

        $nostoProduct->setAvailability($stockStatus);

        $this->applyCategories($nostoProduct, $product, $context);

        $ratingAvg = $this->getValue($product, 'ratingAverage');
        if ($ratingAvg && $this->configProvider->getRatingReviews() === RatingOptions::SHOPWARE_RATINGS) {
            $nostoProduct->setRatingValue(round((float) $ratingAvg, 1));
            $nostoProduct->setReviewCount($this->productHelper->getReviewsCount($this->toSalesChannelEntity($product), $context));
        }

        $purchaseUnit = $this->getValue($product, 'purchaseUnit');
        if ($purchaseUnit !== null) {
            $nostoProduct->setUnitPricingMeasure((string) $purchaseUnit);
        }

        $referenceUnit = $this->getValue($product, 'referenceUnit');
        if ($referenceUnit !== null) {
            $nostoProduct->setUnitPricingBaseMeasure((string) $referenceUnit);
        }

        $unit = $this->getValue($product, 'unit');
        if ($unit && method_exists($unit, 'getTranslation')) {
            $unitName = $unit->getTranslation('name') ?: $unit->getName();
            if (!empty($unitName)) {
                $nostoProduct->setUnitPricingUnit($unitName);
            }
        }

        $children = $this->getValue($product, 'children');
        if ($children instanceof EntityCollection && $children->count()) {
            $skuCollection = new SkuCollection();
            foreach ($children as $child) {
                $skuCollection->append($this->buildSku($child, $context));
            }
            $nostoProduct->setSkus($skuCollection);
        }

        if ($this->getBool($product, 'shippingFree')) {
            $nostoProduct->addCustomField(Builder::SHIPPING_FREE_ATTR_NAME, 'true');
        }

        if ($this->configProvider->isEnabledProductProperties($channelId, $languageId)) {
            $options = $this->getValue($product, 'options');
            if ($options instanceof \Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection) {
                $prepared = $this->productHelper->preparePropertiesOrOptions($options);
                foreach ($prepared as $n => $o) {
                    $nostoProduct->addCustomField($n, $o);
                }
            } elseif (is_iterable($options)) {
                $prepared = $this->productHelper->preparePropertiesOrOptionsGeneric($options);
                foreach ($prepared as $n => $o) {
                    $nostoProduct->addCustomField($n, $o);
                }
            }

            $properties = $this->getValue($product, 'properties');
            if ($properties instanceof \Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection) {
                $prepared = $this->productHelper->preparePropertiesOrOptions($properties);
                foreach ($prepared as $n => $o) {
                    $nostoProduct->addCustomField($n, $o);
                }
            } elseif (is_iterable($properties)) {
                $prepared = $this->productHelper->preparePropertiesOrOptionsGeneric($properties);
                foreach ($prepared as $n => $o) {
                    $nostoProduct->addCustomField($n, $o);
                }
            }

            $this->initTags($product, $nostoProduct, $context);

            $selected = $this->configProvider->getSelectedCustomFields($channelId, $languageId);
            $customFields = $this->getTranslation($product, 'customFields');
            if (is_array($customFields)) {
                foreach ($customFields as $fieldName => $fieldValue) {
                    if (in_array($fieldName, $selected, true) && $fieldValue !== null) {
                        $nostoProduct->addCustomField(mb_strtolower((string) $fieldName), $fieldValue);
                    }
                }
            }
        }

        $cover = $this->getValue($product, 'cover');
        $imageUrl = $this->extractMediaUrl($cover);
        if ($imageUrl) {
//            $nostoProduct->setImageUrl($imageUrl);
//            $nostoProduct->setThumbUrl($imageUrl);
            $nostoProduct->setImageUrl('https://placehold.co/800');
            $nostoProduct->setThumbUrl('https://placehold.co/400');
        } else {
            $placeholder = $this->productHelper->getFallbackImageUrl($context);
            $nostoProduct->setImageUrl($placeholder);
            $nostoProduct->setThumbUrl($placeholder);
        }

        if ($this->configProvider->isEnabledAlternateImages($channelId, $languageId)) {
            $media = $this->getValue($product, 'media');
            if ($media instanceof EntityCollection) {
                $media->sort(static fn ($a, $b): int => ($a->getPosition() ?? 0) <=> ($b->getPosition() ?? 0));
                $urls = array_values(array_filter(
                    $media->map(fn ($m) => $this->extractMediaUrl($m)),
                ));
                $nostoProduct->setAlternateImageUrls($urls);
            }
        }

        $manufacturer = $this->getValue($product, 'manufacturer');
        if ($manufacturer && method_exists($manufacturer, 'getTranslation')) {
            $nostoProduct->setBrand($manufacturer->getTranslation('name'));
            $brandMediaUrl = $this->extractMediaUrl($this->getValue($manufacturer, 'media'));
            if ($brandMediaUrl) {
                $nostoProduct->addCustomField('brand-image-url', $brandMediaUrl);
            }
        }

        $description = $this->getTranslation($product, 'description');
        if (!empty($description)) {
            $nostoProduct->setDescription($description);
        }

        if ($this->configProvider->isEnabledInventoryLevels($channelId, $languageId)) {
            $nostoProduct->setInventoryLevel($stock);
        }

        if ($this->configProvider->isEnabledProductPublishedDateTagging($channelId, $languageId)) {
            $createdAt = $this->getValue($product, 'createdAt');
            if ($createdAt instanceof \DateTimeInterface) {
                $nostoProduct->setDatePublished($createdAt->format('Y-m-d'));
            }
        }

        $this->setPrices($nostoProduct, $product, $context);

        $crossSellings = $this->crossSellingBuilder->build($productId, $context);
        if (!empty($crossSellings)) {
            $nostoProduct->addCustomField('cross-sellings', json_encode($crossSellings));
        }

        if ($this->configProvider->isEnabledProductLabellingSync($channelId, $languageId)) {
            $releaseDate = $this->getValue($product, 'releaseDate');
            $nostoProduct->addCustomField(
                'product-labels_release-date',
                $releaseDate instanceof \DateTimeInterface ? $releaseDate->format(Defaults::STORAGE_DATE_TIME_FORMAT) : null,
            );
            $nostoProduct->addCustomField('product-labels_mfg-part-number', $this->getValue($product, 'manufacturerNumber'));
            $nostoProduct->addCustomField('product-labels_gtin-ean', $this->getValue($product, 'ean'));
        }

        $variantListingConfig = $this->getValue($product, 'variantListingConfig');
        if ($variantListingConfig) {
            $nostoProduct->addCustomField('variant-listing-config', json_encode($variantListingConfig));
        }

        $visibilities = $this->getValue($product, 'visibilities');
        if (is_iterable($visibilities)) {
            foreach ($visibilities as $visibility) {
                $visibilityChannelId = method_exists($visibility, 'getSalesChannelId')
                    ? $visibility->getSalesChannelId()
                    : (method_exists($visibility, 'get') ? $visibility->get('salesChannelId') : null);
                $visibilityValue = method_exists($visibility, 'getVisibility')
                    ? $visibility->getVisibility()
                    : (method_exists($visibility, 'get') ? $visibility->get('visibility') : null);

                if ($channelId === $visibilityChannelId) {
                    switch ($visibilityValue) {
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
                    $nostoProduct->addCustomField(Builder::SHOW_CATEGORY, $showCategory);
                    $nostoProduct->addCustomField(Builder::SHOW_SEARCH, $showSearch);
                    break;
                }
            }
        }

        $keywords = $this->getValue($product, 'customSearchKeywords');
        if (is_array($keywords) && $keywords !== []) {
            $nostoProduct->addCustomField(Builder::SEARCH_KEYWORDS, implode(', ', $keywords));
        }

        return $nostoProduct;
    }

    private function buildSku(object $product, SalesChannelContext $context): NostoSku
    {
        $sku = new NostoSku();
        $channelId = $context->getSalesChannelId();
        $languageId = $context->getLanguageId();
        $productId = $this->getValue($product, 'id');
        $productNumber = $this->getValue($product, 'productNumber');

        $url = $productId ? $this->productHelper->getProductUrlById($productId, $context) : null;
        if (!empty($url)) {
            $sku->setUrl($url);
        }

        $sku->setId(
            $this->configProvider->getProductIdentifier($channelId, $languageId) === ProductIdentifierOptions::PRODUCT_NUMBER
                ? $productNumber
                : $productId,
        );
        $sku->addCustomField(Builder::PRODUCT_NUMBER_KEY, $productNumber);
        $sku->addCustomField(Builder::PRODUCT_ID_KEY, $productId);

        if ($this->getBool($product, 'shippingFree')) {
            $sku->addCustomField(Builder::SHIPPING_FREE_ATTR_NAME, 'true');
        }

        $manufacturer = $this->getValue($product, 'manufacturer');
        if ($manufacturer && method_exists($manufacturer, 'getMedia')) {
            $brandMediaUrl = $this->extractMediaUrl($this->getValue($manufacturer, 'media'));
            if ($brandMediaUrl) {
                $sku->addCustomField('brand-image-url', $brandMediaUrl);
            }
        }

        $crossSellings = $productId ? $this->crossSellingBuilder->build($productId, $context) : [];
        if (!empty($crossSellings)) {
            $sku->addCustomField('cross-sellings', json_encode($crossSellings));
        }

        $name = $this->getTranslation($product, 'name');
        if (!empty($name)) {
            $sku->setName($name);
        }

        $stock = $this->getStock($product, $context);
        $stockStatus = $stock > 0 ? ProductInterface::IN_STOCK : ProductInterface::OUT_OF_STOCK;
        $sku->setAvailability($stockStatus);

        $cover = $this->getValue($product, 'cover');
        $imageUrl = $this->extractMediaUrl($cover);
        if ($imageUrl) {
            $sku->setImageUrl($imageUrl);
        } else {
            $sku->setImageUrl($this->productHelper->getFallbackImageUrl($context));
        }

        $this->setSkuPrices($sku, $product, $context);

        if ($this->configProvider->isEnabledInventoryLevels($channelId, $languageId)) {
            $sku->setInventoryLevel($stock);
        }

        if ($this->configProvider->isEnabledProductProperties($channelId, $languageId)) {
            $options = $this->getValue($product, 'options');
            if ($options instanceof \Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection) {
                $prepared = $this->productHelper->preparePropertiesOrOptions($options);
                foreach ($prepared as $n => $o) {
                    $sku->addCustomField($n, $o);
                }
            } elseif (is_iterable($options)) {
                $prepared = $this->productHelper->preparePropertiesOrOptionsGeneric($options);
                foreach ($prepared as $n => $o) {
                    $sku->addCustomField($n, $o);
                }
            }

            $properties = $this->getValue($product, 'properties');
            if ($properties instanceof \Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection) {
                $prepared = $this->productHelper->preparePropertiesOrOptions($properties);
                foreach ($prepared as $n => $o) {
                    $sku->addCustomField($n, $o);
                }
            } elseif (is_iterable($properties)) {
                $prepared = $this->productHelper->preparePropertiesOrOptionsGeneric($properties);
                foreach ($prepared as $n => $o) {
                    $sku->addCustomField($n, $o);
                }
            }
        }

        if ($this->configProvider->isEnabledProductLabellingSync($channelId, $languageId)) {
            $releaseDate = $this->getValue($product, 'releaseDate');
            $sku->addCustomField(
                'product-labels_release-date',
                $releaseDate instanceof \DateTimeInterface ? $releaseDate->format(Defaults::STORAGE_DATE_TIME_FORMAT) : null,
            );
            $sku->addCustomField('product-labels_mfg-part-number', $this->getValue($product, 'manufacturerNumber'));
            $sku->addCustomField('product-labels_gtin-ean', $this->getValue($product, 'ean'));
        }

        $variantListingConfig = $this->getValue($product, 'variantListingConfig');
        if ($variantListingConfig) {
            $sku->addCustomField('variant-listing-config', json_encode($variantListingConfig));
        }

        $visibilities = $this->getValue($product, 'visibilities');
        if (is_iterable($visibilities)) {
            foreach ($visibilities as $visibility) {
                $visibilityChannelId = method_exists($visibility, 'getSalesChannelId')
                    ? $visibility->getSalesChannelId()
                    : (method_exists($visibility, 'get') ? $visibility->get('salesChannelId') : null);
                $visibilityValue = method_exists($visibility, 'getVisibility')
                    ? $visibility->getVisibility()
                    : (method_exists($visibility, 'get') ? $visibility->get('visibility') : null);

                if ($channelId === $visibilityChannelId) {
                    switch ($visibilityValue) {
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
                    $sku->addCustomField(Builder::SHOW_CATEGORY, $showCategory);
                    $sku->addCustomField(Builder::SHOW_SEARCH, $showSearch);
                    break;
                }
            }
        }

        $keywords = $this->getValue($product, 'customSearchKeywords');
        if (is_array($keywords) && $keywords !== []) {
            $sku->addCustomField(Builder::SEARCH_KEYWORDS, implode(', ', $keywords));
        }

        return $sku;
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
