<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Entity\Product;

use Nosto\Model\Product\Sku as NostoSku;
use Nosto\NostoIntegration\Enums\ProductIdentifierOptions;
use Nosto\NostoIntegration\Enums\StockFieldOptions;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\ProductHelper;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\CrossSelling\CrossSellingBuilder;
use Nosto\NostoIntegration\Utils\NostoCriteriaFactory;
use Nosto\Types\Product\ProductInterface;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SkuBuilder
{
    public function __construct(
        private readonly ConfigProvider $configProvider,
        private readonly ProductHelper $productHelper,
        private readonly CrossSellingBuilder $crossSellingBuilder,
        private readonly EntityRepository $propertyGroupOptionRepository,
    ) {
    }

    /**
     * @var array<string, array<string, string>>
     */
    private array $propertyOptionsCache = [];

    public function build(ProductEntity|PartialEntity|PartialProduct $product, SalesChannelContext $context): NostoSku
    {
        $nostoSku = new NostoSku();
        $channelId = $context->getSalesChannelId();
        $languageId = $context->getLanguageId();

        if ($product instanceof PartialEntity && !$product instanceof PartialProduct) {
            $product = PartialProductConverter::toPartialProduct($product);
        }
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

        if ($product->getShippingFree()) {
            $nostoSku->addCustomField(Builder::SHIPPING_FREE_ATTR_NAME, 'true');
        }

        if ($manufacturer = $product->getManufacturer()) {
            $media = $manufacturer instanceof ProductManufacturerEntity ? $manufacturer->getMedia() : (is_object(
                $manufacturer,
            ) ? $this->getValue(
                $manufacturer,
                'media',
            ) : null);
            $brandMediaUrl = $media instanceof MediaEntity ? $media->getUrl() : (is_object($media) ? $this->getValue(
                $media,
                'url',
            ) : null);
            if (!empty($brandMediaUrl)) {
                $nostoSku->addCustomField('brand-image-url', $brandMediaUrl);
            }
        }

        $crossSellings = $this->crossSellingBuilder->build($product->getId(), $context);

        if (!empty($crossSellings)) {
            $nostoSku->addCustomField('cross-sellings', json_encode($crossSellings));
        }

        $name = $product->getTranslation('name');
        if (!empty($name)) {
            $nostoSku->setName($name);
        }

        $stock = $this->configProvider->getStockField($channelId, $languageId) === StockFieldOptions::ACTUAL_STOCK
            ? $product->getStock()
            : $product->getAvailableStock();
        $stockStatus = $stock > 0 ? ProductInterface::IN_STOCK : ProductInterface::OUT_OF_STOCK;
        $nostoSku->setAvailability($stockStatus);

        $cover = $product->getCover();
        $coverMedia = $cover instanceof ProductMediaEntity ? $cover->getMedia() : (is_object($cover) ? $this->getValue(
            $cover,
            'media',
        ) : null);
        $coverMediaUrl = $coverMedia instanceof MediaEntity ? $coverMedia->getUrl() : (is_object(
            $coverMedia,
        ) ? $this->getValue(
            $coverMedia,
            'url',
        ) : null);
        if (!empty($coverMediaUrl)) {
            $nostoSku->setImageUrl($coverMediaUrl);
        } else {
            $placeholderImageUrl = $this->productHelper->getFallbackImageUrl($context);
            $nostoSku->setImageUrl($placeholderImageUrl);
        }

        $price = $product->getCurrencyPrice($context->getCurrencyId());
        if ($price !== null) {
            $nostoSku->setPrice($price->getGross());

            if ($price->getListPrice() !== null) {
                $nostoSku->setListPrice($price->getListPrice()->getGross());
            }
        }

        if ($this->configProvider->isEnabledInventoryLevels($channelId, $languageId)) {
            $nostoSku->setInventoryLevel($stock);
        }

        if ($this->configProvider->isEnabledProductProperties($channelId, $languageId)) {
            $options = $product->getOptions();
            if ($options !== null) {
                $options = $this->productHelper->preparePropertiesOrOptionsGeneric($options);
                foreach ($options as $name => $option) {
                    $nostoSku->addCustomField(
                        $name,
                        $option,
                    );
                }
            } else {
                $optionIds = $this->getValue($product, 'optionIds');
                $optionsByGroup = $this->getPropertyOptionsByIds($optionIds, $context);
                foreach ($optionsByGroup as $groupName => $values) {
                    $nostoSku->addCustomField($groupName, $values);
                }
            }

            $properties = $product->getProperties();
            if ($properties !== null) {
                $properties = $this->productHelper->preparePropertiesOrOptionsGeneric($properties);
                foreach ($properties as $name => $property) {
                    $nostoSku->addCustomField(
                        $name,
                        $property,
                    );
                }
            } else {
                $propertyIds = $this->getValue($product, 'propertyIds');
                $propertiesByGroup = $this->getPropertyOptionsByIds($propertyIds, $context);
                foreach ($propertiesByGroup as $groupName => $values) {
                    $nostoSku->addCustomField($groupName, $values);
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

        $visibilities = $product->getVisibilities();
        if (is_iterable($visibilities)) {
            foreach ($visibilities as $visibility) {
                $visibilitySalesChannelId = $visibility instanceof ProductVisibilityEntity
                    ? $visibility->getSalesChannelId()
                    : (is_object($visibility) ? $this->getValue($visibility, 'salesChannelId') : null);
                $visibilityValue = $visibility instanceof ProductVisibilityEntity
                    ? $visibility->getVisibility()
                    : (is_object($visibility) ? $this->getValue($visibility, 'visibility') : null);

                if ($channelId === $visibilitySalesChannelId) {
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
                    $nostoSku->addCustomField(Builder::SHOW_CATEGORY, $showCategory);
                    $nostoSku->addCustomField(Builder::SHOW_SEARCH, $showSearch);
                    break;
                }
            }
        }

        if ($keywords = $product->getCustomSearchKeywords()) {
            $nostoSku->addCustomField(Builder::SEARCH_KEYWORDS, implode(', ', $keywords));
        }

        return $nostoSku;
    }

    private function getValue(object $entity, string $field): mixed
    {
        if (method_exists($entity, 'get' . ucfirst($field))) {
            $method = 'get' . ucfirst($field);
            return $entity->$method();
        }
        if (method_exists($entity, 'get')) {
            return $entity->get($field);
        }
        if (property_exists($entity, $field)) {
            return $entity->$field;
        }

        return null;
    }

    /**
     * @param array<int, string>|null $propertyIds
     * @return array<string, string>
     */
    private function getPropertyOptionsByIds(?array $propertyIds, SalesChannelContext $context): array
    {
        if (empty($propertyIds)) {
            return [];
        }

        $cacheKey = md5(implode(',', $propertyIds) . '|' . $context->getLanguageId());
        if (isset($this->propertyOptionsCache[$cacheKey])) {
            return $this->propertyOptionsCache[$cacheKey];
        }

        $criteria = NostoCriteriaFactory::create('product_sync.partialBuilder.sku.property_options');
        $criteria->addAssociation('group');
        $criteria->addFilter(new EqualsAnyFilter('id', $propertyIds));

        $options = $this->propertyGroupOptionRepository->search($criteria, $context->getContext())->getEntities();
        $properties = $this->productHelper->preparePropertiesOrOptions($options);
        $this->propertyOptionsCache[$cacheKey] = $properties;

        return $properties;
    }
}
