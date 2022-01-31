<?php declare(strict_types=1);

namespace Od\NostoIntegration\Model\Nosto\Entity\Product;

use Nosto\Model\Product\Product as NostoProduct;
use Nosto\Model\Product\SkuCollection;
use Nosto\Types\Product\ProductInterface;
use Od\NostoIntegration\Model\ConfigProvider;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class Builder
{
    private SeoUrlPlaceholderHandlerInterface $seoUrlReplacer;
    private ConfigProvider $configProvider;
    private SkuBuilder $skuBuilder;

    public function __construct(
        SeoUrlPlaceholderHandlerInterface $seoUrlReplacer,
        ConfigProvider $configProvider,
        SkuBuilder $skuBuilder
    ) {
        $this->seoUrlReplacer = $seoUrlReplacer;
        $this->configProvider = $configProvider;
        $this->skuBuilder = $skuBuilder;
    }

    public function build(ProductEntity $product, SalesChannelContext $context): NostoProduct
    {
        $channelId = $context->getSalesChannelId();
        $nostoProduct = new NostoProduct();
        $nostoProduct->setUrl($this->getProductUrl($product, $context));
        $nostoProduct->setProductId($product->getId());
        $nostoProduct->setName($product->getTranslation('name'));
        $nostoProduct->setPriceCurrencyCode($context->getCurrency()->getIsoCode());

        $stockStatus = $product->getAvailableStock() > 0 ? ProductInterface::IN_STOCK : ProductInterface::OUT_OF_STOCK;
        $nostoProduct->setAvailability($stockStatus);

        $categoryNames = $product->getCategoriesRo()
            ->filter(fn(CategoryEntity $category) => $category->getParentId() !== null)
            ->map(fn(CategoryEntity $category) => $category->getTranslation('name'));
        if (!empty($categoryNames)) {
            $nostoProduct->setCategories(array_values($categoryNames));
        }

        // todo: set ratings

        if ($this->configProvider->isEnabledVariations($channelId) && $product->getChildCount() !== 0) {
            $skuCollection = new SkuCollection();

            foreach ($product->getChildren()->getElements() as $variationProduct) {
                $skuCollection->append($this->skuBuilder->build($variationProduct, $context));
            }

            $nostoProduct->setSkus($skuCollection);
        }

        if ($this->configProvider->isEnabledProductProperties($channelId) && $product->getOptions() !== null) {
            foreach ($product->getOptions() as $propertyOption) {
                if ($propertyOption->getGroup() !== null) {
                    $nostoProduct->addCustomField($propertyOption->getGroup()->getName(), $propertyOption->getName());
                }
            }

            $tag1Key = $this->configProvider->getTagFieldKey(1, $channelId) ?: null;
            $tag2Key = $this->configProvider->getTagFieldKey(2, $channelId) ?: null;
            $tag3Key = $this->configProvider->getTagFieldKey(3, $channelId) ?: null;

            foreach ($product->getCustomFields() as $fieldName => $fieldValue) {
                switch ($fieldName) {
                    case $tag1Key:
                        $nostoProduct->setTag1($fieldValue);
                        break;
                    case $tag2Key:
                        $nostoProduct->setTag2($fieldValue);
                        break;
                    case $tag3Key:
                        $nostoProduct->setTag3($fieldValue);
                        break;
                }
            }
        }

        if ($product->getCoverId()) {
            $coverMedia = $product->getMedia()->get($product->getCoverId())->getMedia();
            $nostoProduct->setImageUrl($coverMedia->getUrl());
        }

        if ($this->configProvider->isEnabledAlternateImages($channelId)) {
            $alternateMediaUrls = $product->getMedia()->map(fn(ProductMediaEntity $media) => $media->getMedia()->getUrl());
            $nostoProduct->setAlternateImageUrls($alternateMediaUrls);
        }

        if ($manufacturer = $product->getManufacturer()) {
            $nostoProduct->setBrand($manufacturer->getTranslation('name'));
        }

        if ($description = $product->getTranslation('description')) {
            $nostoProduct->setDescription($description);
        }

        if ($this->configProvider->isEnabledInventoryLevels($channelId)) {
            $nostoProduct->setInventoryLevel($product->getAvailableStock());
        }

        if ($this->configProvider->isEnabledProductPublishedDateTagging()) {
            $nostoProduct->setDatePublished($product->getCreatedAt()->format('Y-m-d'));
        }

        if ($price = $product->getCurrencyPrice($context->getCurrencyId())) {
            $nostoProduct->setPrice($price->getGross());
        }

        if ($price->getListPrice() !== null) {
            $nostoProduct->setListPrice($price->getListPrice()->getGross());
        }

        if ($ean = $product->getEan()) {
            $nostoProduct->setGtin($ean);
        }

        return $nostoProduct;
    }

    protected function getProductUrl(ProductEntity $product, SalesChannelContext $context)
    {
        if ($domains = $context->getSalesChannel()->getDomains()) {
            $raw = $this->seoUrlReplacer->generate('frontend.detail.page', ['productId' => $product->getId()]);

            return $this->seoUrlReplacer->replace($raw, $domains->first()->getUrl(), $context);
        }

        throw new \Exception('Unable to generate SEO Product URL.');
    }
}
