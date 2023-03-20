<?php declare(strict_types=1);

namespace Od\NostoIntegration\Model\Nosto\Entity\Product;

use Nosto\Helper\SerializationHelper;
use Nosto\Model\Product\Product as NostoProduct;
use Nosto\Model\Product\SkuCollection;
use Nosto\Types\Product\ProductInterface;
use Od\NostoIntegration\Model\ConfigProvider;
use Od\NostoIntegration\Model\Nosto\Entity\Helper\ProductHelper;
use Od\NostoIntegration\Model\Nosto\Entity\Product\Category\TreeBuilderInterface;
use Od\NostoIntegration\Model\Nosto\Entity\Product\CrossSelling\CrossSellingBuilderInterface;
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
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Tag\TagCollection;
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
    private CrossSellingBuilderInterface $crossSellingBuilder;
    private EntityRepositoryInterface $tagRepository;

    public function __construct(
        SeoUrlPlaceholderHandlerInterface $seoUrlReplacer,
        ConfigProvider $configProvider,
        ProductHelper $productHelper,
        NetPriceCalculator $calculator,
        CashRounding $priceRounding,
        SkuBuilderInterface $skuBuilder,
        TreeBuilderInterface $treeBuilder,
        EventDispatcherInterface $eventDispatcher,
        CrossSellingBuilderInterface $crossSellingBuilder,
        EntityRepositoryInterface $tagRepository
    ) {
        $this->seoUrlReplacer = $seoUrlReplacer;
        $this->configProvider = $configProvider;
        $this->productHelper = $productHelper;
        $this->skuBuilder = $skuBuilder;
        $this->treeBuilder = $treeBuilder;
        $this->calculator = $calculator;
        $this->priceRounding = $priceRounding;
        $this->eventDispatcher = $eventDispatcher;
        $this->crossSellingBuilder = $crossSellingBuilder;
        $this->tagRepository = $tagRepository;
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
        $nostoProduct->addCustomField('productNumber', $product->getProductNumber());
        $name = $product->getTranslation('name');
        if (!empty($name)) {
            $nostoProduct->setName($name);
        }

        $nostoProduct->setPriceCurrencyCode($context->getCurrency()->getIsoCode());
        $stock = $this->configProvider->getStockField($context->getSalesChannelId()) === 'actual-stock' ? $product->getStock() : $product->getAvailableStock();
        $stockStatus = $stock > 0 ? ProductInterface::IN_STOCK : ProductInterface::OUT_OF_STOCK;
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

            $this->initTags($product, $nostoProduct, $context);
            $selectedCustomFieldsCustomFields = $this->configProvider->getSelectedCustomFields($channelId);

            foreach ($product->getCustomFields() as $fieldName => $fieldOriginalValue) {
                // All non-scalar value should be serialized
                $fieldValue = $fieldOriginalValue === null || \is_scalar($fieldOriginalValue) ?
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
            $nostoProduct->setInventoryLevel($stock);
        }

        if ($this->configProvider->isEnabledProductPublishedDateTagging()) {
            $nostoProduct->setDatePublished($product->getCreatedAt()->format('Y-m-d'));
        }

        if ($ean = $product->getEan()) {
            $nostoProduct->setGtin($ean);
        }

        $this->setPrices($nostoProduct, $product, $context);

        $crossSellings = $this->crossSellingBuilder->build($product->getId(), $context);

        if(!empty($crossSellings)) {
            $nostoProduct->addCustomField('cross-sellings', json_encode($crossSellings));
        }

        if ($this->configProvider->isEnabledProductLabellingSync($context->getSalesChannelId())) {
            $nostoProduct->addCustomField('product-labels', json_encode(
                    [
                        'release-date' => $product->getReleaseDate() ? $product->getReleaseDate()->format(Defaults::STORAGE_DATE_TIME_FORMAT) : null,
                        'mfg-part-number' => $product->getManufacturerNumber()
                    ]
                )
            );
        }

        if(method_exists($product, 'getVariantListingConfig') && $product->getVariantListingConfig()) {
            $nostoProduct->addCustomField('variant-listing-config', json_encode($product->getVariantListingConfig()));
        }

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
            $price = $this->calculator->calculate(
                new QuantityPriceDefinition($unitPrice, $productPrice->getTaxRules(), 1),
                $context->getItemRounding()
            );

            $priceList = $this->calculator->calculate(
                new QuantityPriceDefinition($listPrice, $productPrice->getTaxRules(), 1),
                $context->getItemRounding()
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

    private function getProductUrl(ProductEntity $product, SalesChannelContext $context)
    {
        if ($domains = $context->getSalesChannel()->getDomains()) {
            $domainId = (string) $this->configProvider->getDomainId($context->getSalesChannelId());
            $domain = $domains->has($domainId) ? $domains->get($domainId) : $domains->first();
            $raw = $this->seoUrlReplacer->generate('frontend.detail.page', ['productId' => $product->getId()]);
            return $this->seoUrlReplacer->replace($raw, $domain != null ? $domain->getUrl() : '', $context);
        }

        return null;
    }

    private function initTags(ProductEntity $productEntity, NostoProduct $nostoProduct, SalesChannelContext $context): void
    {
        $tags = $this->loadTags($context->getContext());
        $nostoProduct->setTag1($this->getTagValues($productEntity, $this->configProvider->getTagFieldKey(1, $context->getSalesChannelId()), $tags));
        $nostoProduct->setTag2($this->getTagValues($productEntity, $this->configProvider->getTagFieldKey(2, $context->getSalesChannelId()), $tags));
        $nostoProduct->setTag3($this->getTagValues($productEntity, $this->configProvider->getTagFieldKey(3, $context->getSalesChannelId()), $tags));
    }

    private function getTagValues(ProductEntity $productEntity, array $tagIds, TagCollection $allTags): array
    {
        $result = [];
        foreach ($tagIds as $tagId) {
            if ($allTags->has($tagId) && !empty($productEntity->getTagIds()) && in_array($tagId, $productEntity->getTagIds())) {
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
}
