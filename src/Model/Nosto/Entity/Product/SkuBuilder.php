<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Entity\Product;

use Nosto\Helper\SerializationHelper;
use Nosto\Model\Product\Sku as NostoSku;
use Nosto\NostoIntegration\Enums\ProductIdentifierOptions;
use Nosto\NostoIntegration\Enums\StockFieldOptions;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\ProductHelper;
use Nosto\Types\Product\ProductInterface;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SkuBuilder
{
    public function __construct(
        private readonly ConfigProvider $configProvider,
        private readonly ProductHelper $productHelper,
    ) {
    }

    public function build(ProductEntity $product, SalesChannelContext $context): NostoSku
    {
        $nostoSku = new NostoSku();
        $channelId = $context->getSalesChannelId();
        $languageId = $context->getLanguageId();

        $url = $this->productHelper->getProductUrl($product, $context);
        if (!empty($url)) {
            $nostoSku->setUrl($url);
        }

        $nostoSku->setId(
            $this->configProvider->getProductIdentifier(
                $channelId,
                $languageId,
            ) === ProductIdentifierOptions::PRODUCT_NUMBER
                ? $product->getProductNumber()
                : $product->getId(),
        );
        $nostoSku->addCustomField(Builder::PRODUCT_NUMBER_KEY, $product->getProductNumber());
        $nostoSku->addCustomField(Builder::PRODUCT_ID_KEY, $product->getId());

        $name = $product->getTranslation('name');
        if (!empty($name)) {
            $nostoSku->setName($name);
        }

        $stock = $this->configProvider->getStockField($channelId, $languageId) === StockFieldOptions::ACTUAL_STOCK
            ? $product->getStock()
            : $product->getAvailableStock();
        $stockStatus = $stock > 0 ? ProductInterface::IN_STOCK : ProductInterface::OUT_OF_STOCK;
        $nostoSku->setAvailability($stockStatus);

        if ($product->getCover() && $product->getCover()->getMedia()) {
            $nostoSku->setImageUrl($product->getCover()->getMedia()->getUrl());
        } else {
            $placeholderImageUrl = $this->productHelper->getFallbackImageUrl($context);
            $nostoSku->setImageUrl($placeholderImageUrl);
        }

        if ($price = $product->getCurrencyPrice($context->getCurrencyId())) {
            $nostoSku->setPrice($price->getGross());
        }

        if ($price->getListPrice() !== null) {
            $nostoSku->setListPrice($price->getListPrice()->getGross());
        }

        if ($this->configProvider->isEnabledInventoryLevels($channelId, $languageId)) {
            $nostoSku->setInventoryLevel($stock);
        }

        if (
            $this->configProvider->isEnabledProductProperties($channelId, $languageId) &&
            $product->getOptions() !== null
        ) {
            $options = $this->productHelper->preparePropertiesOrOptions($product->getOptions());
            foreach ($options as $name => $option) {
                $nostoSku->addCustomField(
                    $name,
                    $option,
                );
            }
            $properties = $this->productHelper->preparePropertiesOrOptions($product->getProperties());
            foreach ($properties as $name => $property) {
                $nostoSku->addCustomField(
                    $name,
                    $property,
                );
            }

            // Add custom fields processing for SKUs (same logic as main product)
            $selectedCustomFieldsCustomFields = $this->configProvider->getSelectedCustomFields($channelId, $languageId);

            if ($product->getCustomFields() !== null) {
                foreach ($product->getCustomFields() as $fieldName => $fieldOriginalValue) {
                    // All non-scalar value should be serialized
                    $fieldValue = $fieldOriginalValue === null || is_scalar($fieldOriginalValue) ?
                        $fieldOriginalValue : SerializationHelper::serialize($fieldOriginalValue);

                    if (in_array($fieldName, $selectedCustomFieldsCustomFields) && $fieldValue !== null) {
                        $nostoSku->addCustomField(mb_strtolower($fieldName), $fieldValue);
                    }
                }
            }
        }

        if ($this->configProvider->isEnabledProductLabellingSync($channelId, $languageId)) {
            $nostoSku->addCustomField(
                'product-labels_release-date',
                $product->getReleaseDate()?->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            );
            $nostoSku->addCustomField('product-labels_mfg-part-number', $product->getManufacturerNumber());
            $nostoSku->addCustomField('product-labels_gtin-ean', $product->getEan());
        }

        if (method_exists($product, 'getVariantListingConfig') && $product->getVariantListingConfig()) {
            $nostoSku->addCustomField('variant-listing-config', json_encode($product->getVariantListingConfig()));
        }

        return $nostoSku;
    }
}
