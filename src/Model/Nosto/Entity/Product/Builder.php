<?php declare(strict_types=1);

namespace Od\NostoIntegration\Model\Nosto\Entity\Product;

use Nosto\Model\Product\Product as NostoProduct;
use Nosto\Model\Product\SkuCollection;
use Nosto\Types\Product\ProductInterface;
use Od\NostoIntegration\Model\ConfigProvider;
use Od\NostoIntegration\Model\Nosto\Entity\Helper\ProductHelper;
use Od\NostoIntegration\Model\Nosto\Entity\Product\Category\TreeBuilderInterface;
use Od\NostoIntegration\Model\Nosto\Entity\Product\Event\NostoProductBuiltEvent;
use Od\NostoIntegration\Service\CategoryMerchandising\Translator\ShippingFreeFilterTranslator;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Cart\Price\NetPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class Builder implements BuilderInterface
{
    private SeoUrlPlaceholderHandlerInterface $seoUrlReplacer;
    private ConfigProvider $configProvider;
    private ProductHelper $productHelper;
    private SkuBuilderInterface $skuBuilder;
    private TreeBuilderInterface $treeBuilder;
    private EventDispatcherInterface $eventDispatcher;
    private NetPriceCalculator $calculator;
    private CashRounding $priceRounding;

    public function __construct(
        SeoUrlPlaceholderHandlerInterface $seoUrlReplacer,
        ConfigProvider $configProvider,
        ProductHelper $productHelper,
        NetPriceCalculator $calculator,
        CashRounding $priceRounding,
        SkuBuilderInterface $skuBuilder,
        TreeBuilderInterface $treeBuilder,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->seoUrlReplacer = $seoUrlReplacer;
        $this->configProvider = $configProvider;
        $this->productHelper = $productHelper;
        $this->skuBuilder = $skuBuilder;
        $this->treeBuilder = $treeBuilder;
        $this->calculator = $calculator;
        $this->priceRounding = $priceRounding;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function build(SalesChannelProductEntity $product, SalesChannelContext $context): NostoProduct
    {
        if ($product->getCategoriesRo() === null) {
            $product = $this->productHelper->reloadProduct($product->getId(), $context);
        }

        $channelId = $context->getSalesChannelId();
        $nostoProduct = new NostoProduct();
        $url = $this->getProductUrl($product, $context);
        if (!empty($url)) {
            $nostoProduct->setUrl($url);
        }

        $nostoProduct->setProductId($product->getId());
        $name = $product->getTranslation('name');
        if (!empty($name)) {
            $nostoProduct->setName($name);
        }

        $nostoProduct->setPriceCurrencyCode($context->getCurrency()->getIsoCode());

        $stockStatus = $product->getAvailableStock() > 0 ? ProductInterface::IN_STOCK : ProductInterface::OUT_OF_STOCK;
        $nostoProduct->setAvailability($stockStatus);

        $nostoCategoryNames = $this->treeBuilder->fromCategoriesRo($product->getCategoriesRo());
        if (!empty($nostoCategoryNames)) {
            $nostoProduct->setCategories($nostoCategoryNames);
        }

        if ($ratingAvg = $product->getRatingAverage()) {
            $nostoProduct->setRatingValue($ratingAvg);
            $nostoProduct->setReviewCount($this->productHelper->getReviewsCount($product, $context));
        }

        if ($this->configProvider->isEnabledVariations($channelId) && $product->getChildCount() !== 0) {
            $skuCollection = new SkuCollection();

            foreach ($product->getChildren()->getElements() as $variationProduct) {
                $skuCollection->append($this->skuBuilder->build($variationProduct, $context));
            }

            $nostoProduct->setSkus($skuCollection);
        }

        if ($product->getShippingFree()) {
            $nostoProduct->addCustomField(ShippingFreeFilterTranslator::SHIPPING_FREE_ATTR_NAME, 'true');
        }

        if ($this->configProvider->isEnabledProductProperties($channelId) && $product->getOptions() !== null) {
            foreach ($product->getOptions() as $option) {
                if ($option->getGroup() !== null) {
                    $nostoProduct->addCustomField($option->getGroup()->getName(), $option->getName());
                }
            }
            foreach ($product->getProperties() as $property) {
                if ($property->getGroup() !== null) {
                    $nostoProduct->addCustomField($property->getGroup()->getName(), $property->getName());
                }
            }

            $tag1Keys = $this->configProvider->getTagFieldKey(1, $channelId);
            $tag2Keys = $this->configProvider->getTagFieldKey(2, $channelId);
            $tag3Keys = $this->configProvider->getTagFieldKey(3, $channelId);

            $selectedCustomFieldsCustomFields = $this->configProvider->getSelectedCustomFields($channelId);
            $tag1Values = $tag2Values = $tag3Values = [];

            foreach ($product->getCustomFields() as $fieldName => $fieldValue) {
                if (in_array($fieldName, $selectedCustomFieldsCustomFields) && $fieldValue !== null) {
                    $nostoProduct->addCustomField($fieldName, $fieldValue);
                }
                if (in_array($fieldName, $tag1Keys)) {
                    $tag1Values[] = $fieldValue;
                }
                if (in_array($fieldName, $tag2Keys)) {
                    $tag2Values[] = $fieldValue;
                }
                if (in_array($fieldName, $tag3Keys)) {
                    $tag3Values[] = $fieldValue;
                }
            }

            $nostoProduct->setTag1($tag1Values);
            $nostoProduct->setTag2($tag2Values);
            $nostoProduct->setTag3($tag3Values);
        }

        if ($product->getCover()) {
            $nostoProduct->setImageUrl($product->getCover()->getMedia()->getUrl());
            $nostoProduct->setThumbUrl($product->getCover()->getMedia()->getUrl());
        }

        if ($this->configProvider->isEnabledAlternateImages($channelId)) {
            $alternateMediaUrls = $product->getMedia()->map(fn(ProductMediaEntity $media) => $media->getMedia()->getUrl());
            $nostoProduct->setAlternateImageUrls(array_values($alternateMediaUrls));
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

        if ($ean = $product->getEan()) {
            $nostoProduct->setGtin($ean);
        }

        $this->setPrices($nostoProduct, $product, $context);
        $this->eventDispatcher->dispatch(new NostoProductBuiltEvent($product, $nostoProduct, $context));

        return $nostoProduct;
    }

    private function setPrices(
        NostoProduct $nostoProdcut,
        SalesChannelProductEntity $product,
        SalesChannelContext $context
    ): void {
        $productPrice = $product->getCalculatedPrices()->first() ?: $product->getCalculatedPrice();
        if (!($productPrice instanceof CalculatedPrice)) {
            return;
        }
        $listPrice = $productPrice->getListPrice() ? $productPrice->getListPrice()->getPrice() : $productPrice->getUnitPrice();
        $unitPrice = $productPrice->getUnitPrice();
        $isGross = empty($context->getCurrentCustomerGroup()) || $context->getCurrentCustomerGroup()->getDisplayGross();

        if (!$isGross) {
            $unitPrice = $listPrice = 0;
            $price = $this->calculator->calculate(
                new QuantityPriceDefinition($unitPrice, $productPrice->getTaxRules(), 1),
                $context->getItemRounding()
            );
            $priceList = $this->calculator->calculate(
                new QuantityPriceDefinition($listPrice, $productPrice->getTaxRules(), 1),
                $context->getItemRounding()
            );

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

    private function getProductUrl(ProductEntity $product, SalesChannelContext $context)
    {
        if ($domains = $context->getSalesChannel()->getDomains()) {
            $domainId = $this->configProvider->getDomainId($context->getSalesChannelId());
            $domain = $domainId !== null || $domains->get($domainId) instanceof SalesChannelDomainEntity ? $domains->get($domainId) : $domains->first();
            $raw = $this->seoUrlReplacer->generate('frontend.detail.page', ['productId' => $product->getId()]);

            return $this->seoUrlReplacer->replace($raw, $domain != null ? $domain->getUrl() : '', $context);
        }

        return null;
    }
}
