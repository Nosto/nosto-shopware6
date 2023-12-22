<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Entity\Product;

use Exception;
use Nosto\Helper\SerializationHelper;
use Nosto\Model\Product\Product as NostoProduct;
use Nosto\Model\Product\SkuCollection;
use Nosto\NostoException;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\ProductHelper;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\Category\TreeBuilderInterface;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\CrossSelling\CrossSellingBuilderInterface;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\Event\NostoProductBuiltEvent;
use Nosto\NostoIntegration\Service\CategoryMerchandising\Translator\ShippingFreeFilterTranslator;
use Nosto\Types\Product\ProductInterface;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Cart\Price\NetPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
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
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class Builder implements BuilderInterface
{
    public const PRODUCT_ASSIGNMENT_TYPE = 'productAssignmentType';

    public function __construct(
        private readonly ConfigProvider $configProvider,
        private readonly ProductHelper $productHelper,
        private readonly NetPriceCalculator $calculator,
        private readonly CashRounding $priceRounding,
        private readonly SkuBuilderInterface $skuBuilder,
        private readonly TreeBuilderInterface $treeBuilder,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly CrossSellingBuilderInterface $crossSellingBuilder,
        private readonly EntityRepository $tagRepository,
        private SalesChannelRepository $categoryRepository,
    ) {
    }

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
            $this->configProvider->getProductIdentifier($channelId, $languageId) === 'product-number'
                ? $product->getProductNumber()
                : $product->getId(),
        );
        $nostoProduct->addCustomField('productNumber', $product->getProductNumber());
        $nostoProduct->addCustomField('productId', $product->getId());
        $name = $product->getTranslation('name');
        if (!empty($name)) {
            $nostoProduct->setName($name);
        }

        $nostoProduct->setPriceCurrencyCode($context->getCurrency()->getIsoCode());
        $stock = $this->configProvider->getStockField($channelId, $languageId) === 'actual-stock'
            ? $product->getStock()
            : $product->getAvailableStock();
        $stockStatus = $stock > 0 ? ProductInterface::IN_STOCK : ProductInterface::OUT_OF_STOCK;

        if (!$product->getIsCloseout() && $stock < 1) {
            $stockStatus = ProductInterface::IN_STOCK;
        }

        $nostoProduct->setAvailability($stockStatus);

        if ($this->configProvider->getCategoryNamingOption($channelId, $languageId) === 'with-id') {
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

        if ($ratingAvg = $product->getRatingAverage()) {
            $nostoProduct->setRatingValue(round($ratingAvg, 1));
            $nostoProduct->setReviewCount($this->productHelper->getReviewsCount($product, $context));
        }

        if ($this->configProvider->isEnabledVariations($channelId, $languageId) && $product->getChildren()->count()) {
            $skuCollection = $this->preparingChildrenSkuCollection($product, $context);

            $nostoProduct->setSkus($skuCollection);
        }

        if ($product->getShippingFree()) {
            $nostoProduct->addCustomField(ShippingFreeFilterTranslator::SHIPPING_FREE_ATTR_NAME, 'true');
        }

        if (
            $this->configProvider->isEnabledProductProperties($channelId, $languageId) &&
            $product->getOptions() !== null
        ) {
            foreach ($product->getOptions() as $option) {
                if ($option->getGroup() !== null) {
                    $nostoProduct->addCustomField(
                        $option->getGroup()->getTranslation('name'),
                        $option->getTranslation('name'),
                    );
                }
            }
            foreach ($product->getProperties() as $property) {
                if ($property->getGroup() !== null) {
                    $nostoProduct->addCustomField(
                        $property->getGroup()->getTranslation('name'),
                        $property->getTranslation('name'),
                    );
                }
            }

            $this->initTags($product, $nostoProduct, $context);
            $selectedCustomFieldsCustomFields = $this->configProvider->getSelectedCustomFields($channelId, $languageId);

            foreach ($product->getCustomFields() as $fieldName => $fieldOriginalValue) {
                // All non-scalar value should be serialized
                $fieldValue = $fieldOriginalValue === null || is_scalar($fieldOriginalValue) ?
                    $fieldOriginalValue : SerializationHelper::serialize($fieldOriginalValue);

                if (in_array($fieldName, $selectedCustomFieldsCustomFields) && $fieldValue !== null) {
                    $nostoProduct->addCustomField($fieldName, $fieldValue);
                }
            }
        }

        if ($product->getCover()) {
            $nostoProduct->setImageUrl($product->getCover()->getMedia()->getUrl());
            $nostoProduct->setThumbUrl($product->getCover()->getMedia()->getUrl());
        }

        if ($this->configProvider->isEnabledAlternateImages($channelId, $languageId)) {
            $alternateMediaUrls = $product->getMedia()->map(
                fn (ProductMediaEntity $media) => $media->getMedia()->getUrl(),
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

        if ($ean = $product->getEan()) {
            $nostoProduct->setGtin($ean);
        }

        $this->setPrices($nostoProduct, $product, $context);

        $crossSellings = $this->crossSellingBuilder->build($product->getId(), $context);

        if (!empty($crossSellings)) {
            $nostoProduct->addCustomField('cross-sellings', json_encode($crossSellings));
        }

        if ($this->configProvider->isEnabledProductLabellingSync($channelId, $languageId)) {
            $nostoProduct->addCustomField(
                'product-labels',
                json_encode(
                    [
                        'release-date' => $product->getReleaseDate()?->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                        'mfg-part-number' => $product->getManufacturerNumber(),
                    ],
                ),
            );
        }

        if (method_exists($product, 'getVariantListingConfig') && $product->getVariantListingConfig()) {
            $nostoProduct->addCustomField('variant-listing-config', json_encode($product->getVariantListingConfig()));
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
        ProductEntity $productEntity,
        NostoProduct $nostoProduct,
        SalesChannelContext $context,
    ): void {
        $tags = $this->loadTags($context->getContext());
        $channelId = $context->getSalesChannelId();
        $languageId = $context->getLanguageId();

        $nostoProduct->setTag1($this->getTagValues(
            $productEntity,
            $this->configProvider->getTagFieldKey(1, $channelId, $languageId),
            $tags,
        ));
        $nostoProduct->setTag2($this->getTagValues(
            $productEntity,
            $this->configProvider->getTagFieldKey(2, $channelId, $languageId),
            $tags,
        ));
        $nostoProduct->setTag3($this->getTagValues(
            $productEntity,
            $this->configProvider->getTagFieldKey(3, $channelId, $languageId),
            $tags,
        ));
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

    private function loadTags(Context $context): TagCollection
    {
        $criteria = new Criteria();

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

    private function makeActualProductCategories(
        ?SalesChannelProductEntity $product,
        SalesChannelContext $context,
    ): void {
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
        }
    }

    private function getCategoriesWithDynamicProductGroups(SalesChannelContext $context): CategoryCollection
    {
        $criteria = new Criteria();
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

        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsAnyFilter('id', $categoriesPaths),
        );

        return $this->categoryRepository->search($criteria, $context)->getEntities();
    }

    private function preparingChildrenSkuCollection(ProductEntity $product, SalesChannelContext $context): SkuCollection
    {
        $skuCollection = new SkuCollection();

        if ($product->getChildren()->count()) {
            $salesChannelId = $context->getSalesChannelId();
            $languageId = $context->getLanguageId();

            $criteria = new Criteria();
            $criteria->addAssociation('media');
            $criteria->addAssociation('cover');
            $criteria->addAssociation('options.group');
            $criteria->addAssociation('properties.group');
            $criteria->addAssociation('manufacturer');
            $criteria->addAssociation('categoriesRo');
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
                foreach ($children as $variationProduct) {
                    $skuCollection->append($this->skuBuilder->build($variationProduct, $context));
                }
            }
        }

        return $skuCollection;
    }
}
