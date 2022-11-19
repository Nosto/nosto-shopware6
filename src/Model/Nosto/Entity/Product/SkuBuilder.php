<?php declare(strict_types=1);

namespace Od\NostoIntegration\Model\Nosto\Entity\Product;

use Nosto\Model\Product\Sku as NostoSku;
use Nosto\Types\Product\ProductInterface;
use Od\NostoIntegration\Model\ConfigProvider;
use Shopware\Core\Content\Product\ProductEntity;
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
        $nostoSku->setId($product->getId());

        $name = $product->getTranslation('name');
        if (!empty($name)) {
            $nostoSku->setName($name);
        }

        $stockStatus = $product->getAvailableStock() > 0 ? ProductInterface::IN_STOCK : ProductInterface::OUT_OF_STOCK;
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

        if ($this->configProvider->isEnabledInventoryLevels()) {
            $nostoSku->setInventoryLevel($product->getAvailableStock());
        }

        if ($ean = $product->getEan()) {
            $nostoSku->setGtin($ean);
        }

        if ($this->configProvider->isEnabledProductProperties() && $product->getOptions() !== null) {
            foreach ($product->getOptions() as $propertyOption) {
                if ($propertyOption->getGroup() !== null) {
                    $nostoSku->addCustomField($propertyOption->getGroup()->getName(), $propertyOption->getName());
                }
            }
        }

        return $nostoSku;
    }
}
