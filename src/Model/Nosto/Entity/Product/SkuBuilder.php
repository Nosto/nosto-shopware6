<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Entity\Product;

use Nosto\Model\Product\Sku as NostoSku;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\Types\Product\ProductInterface;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SkuBuilder implements SkuBuilderInterface
{
    private ConfigProvider $configProvider;

    public function __construct(ConfigProvider $configProvider)
    {
        $this->configProvider = $configProvider;
    }

    public function build(ProductEntity $product, SalesChannelContext $context): NostoSku
    {
        $nostoSku = new NostoSku();
        $channelId = $context->getSalesChannelId();
        $languageId = $context->getLanguageId();

        $nostoSku->setId(
            $this->configProvider->getProductIdentifier($channelId, $languageId) === 'product-number'
                ? $product->getProductNumber()
                : $product->getId(),
        );
        $nostoSku->addCustomField('productNumber', $product->getProductNumber());
        $nostoSku->addCustomField('productId', $product->getId());

        $name = $product->getTranslation('name');
        if (!empty($name)) {
            $nostoSku->setName($name);
        }

        $stock = $this->configProvider->getStockField($channelId, $languageId) === 'actual-stock'
            ? $product->getStock()
            : $product->getAvailableStock();
        $stockStatus = $stock > 0 ? ProductInterface::IN_STOCK : ProductInterface::OUT_OF_STOCK;
        $nostoSku->setAvailability($stockStatus);

        if ($product->getCover() && $product->getCover()->getMedia()) {
            $nostoSku->setImageUrl($product->getCover()->getMedia()->getUrl());
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

        if ($ean = $product->getEan()) {
            $nostoSku->setGtin($ean);
        }

        if (
            $this->configProvider->isEnabledProductProperties($channelId, $languageId) &&
            $product->getOptions() !== null
        ) {
            foreach ($product->getOptions() as $propertyOption) {
                if ($propertyOption->getGroup() !== null) {
                    $nostoSku->addCustomField($propertyOption->getGroup()->getName(), $propertyOption->getName());
                }
            }
        }

        if ($this->configProvider->isEnabledProductLabellingSync($channelId, $languageId)) {
            $nostoSku->addCustomField(
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
            $nostoSku->addCustomField('variant-listing-config', json_encode($product->getVariantListingConfig()));
        }

        return $nostoSku;
    }
}
