<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Twig\Extension;

use Nosto\Model\Category\Category as NostoCategory;
use Nosto\Model\Product\Product as NostoProduct;
use Nosto\NostoIntegration\Model\Config\NostoConfigService;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Category\Builder as CategoryBuilder;
use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\ProductHelper;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\PartialProduct;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\PartialProductConverter;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\PartialProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\ProductProviderInterface;
use Nosto\NostoIntegration\Utils\Logger\ContextHelper;
use Nosto\NostoIntegration\Utils\NostoCriteriaFactory;
use Nosto\NostoIntegration\Utils\ProductTaggingHelper;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Throwable;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class NostoExtension extends AbstractExtension
{
    public function __construct(
        private readonly ProductProviderInterface $productProvider,
        private readonly PartialProvider $partialProductProvider,
        private readonly LoggerInterface $logger,
        private readonly SalesChannelRepository $salesChannelProductRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly ConfigProvider $configProvider,
        private readonly NostoConfigService $nostoConfigService,
        private readonly EntityRepository $productRepository,
        private readonly EntityRepository $categoryRepository,
        private readonly CategoryBuilder $nostoCategoryBuilder,
        private readonly ProductHelper $productHelper,
    ) {
    }

    /**
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('nosto_product', [$this, 'getNostoProduct']),
            new TwigFunction('nosto_page_type', [$this, 'getPageType']),
            new TwigFunction('nosto_shopware_product_by_id', [$this, 'getShopwareProductByID']),
            new TwigFunction('nosto_shopware_main_product_identifier', [$this, 'getShopwareMainProductIdentifier']),
            new TwigFunction('nosto_configuration_values', [$this, 'getNostoConfigurationValues']),
            new TwigFunction('nosto_category', [$this, 'getNostoCategory']),
        ];
    }

    public function getNostoProduct(
        SalesChannelProductEntity|PartialProduct|PartialEntity|null $product,
        SalesChannelContext $context,
    ): ?NostoProduct {
        try {
            if ($product === null) {
                return null;
            }

            if ($product instanceof SalesChannelProductEntity) {
                $partialProduct = $this->productHelper
                    ->getShopwareProductsPartial([$product->getId()], $context, false)
                    ->get($product->getId());

                if ($partialProduct instanceof PartialEntity && !$partialProduct instanceof PartialProduct) {
                    $partialProduct = PartialProductConverter::toPartialProduct($partialProduct);
                }

                if ($partialProduct instanceof PartialProduct) {
                    $result = $this->partialProductProvider->get($partialProduct, $context);
                } else {
                    $result = $this->productProvider->get($product, $context);
                }
            } else {
                if ($product instanceof PartialEntity && !$product instanceof PartialProduct) {
                    $product = PartialProductConverter::toPartialProduct($product);
                }
                $result = $this->partialProductProvider->get($product, $context);
            }

            if ($product) {
                $variationStatus = $this->resolveVariations($product);
                $result->variationStatus = $variationStatus;
            }

            return $result;
        } catch (Throwable $throwable) {
            $this->logger->error(
                $throwable->getMessage(),
                ContextHelper::createContextFromException($throwable),
            );

            return null;
        }
    }

    private function resolveVariations($product): string
    {
        if ($variantsConfig = $product->getVariantListingConfig()) {
            if (!$variantsConfig->getDisplayParent() && $variantsConfig->getMainVariantId()) {
                return 'singleProduct';
            } elseif ($variantsConfig->getDisplayParent()) {
                return 'singleProduct';
            } else {
                return 'variantProducts';
            }
        }

        return 'nothing';
    }

    public function getShopwareProductByID($id, SalesChannelContext $context): ?SalesChannelProductEntity
    {
        $criteria = NostoCriteriaFactory::create();
        $criteria->addFilter(new EqualsFilter('id', $id));

        return $this->salesChannelProductRepository
            ->search($criteria, $context)
            ->first();
    }

    public function getShopwareMainProductIdentifier(
        $id,
        $variantId,
        SalesChannelContext $context,
        bool $isProductTagging = false,
    ): string|NostoProduct {
        $criteria = NostoCriteriaFactory::create();
        $criteria->addFilter(new EqualsFilter('id', $id));
        $criteria->addFields([
            'id',
            'parentId',
            'productNumber',
            'active',
            'childCount',
            'displayGroup',
            'isCloseout',
            'availableStock',
            'stock',
            'createdAt',
            'releaseDate',
            'ratingAverage',
            'variantListingConfig',
            'manufacturerNumber',
            'ean',
            'purchaseUnit',
            'referenceUnit',
            'shippingFree',
            'customSearchKeywords',
            'tagIds',
            'streamIds',
            'optionIds',
            'propertyIds',
            'unitId',
            'taxId',
            'price',
            'prices',
            'name',
            'description',
            'customFields',
            'keywords',
            'packUnit',
            'packUnitPlural',
            'metaTitle',
            'metaDescription',
        ]);

        $criteria->addFields([
            'cover.id',
            'cover.media.id',
            'cover.media.url',
            'manufacturer.id',
            'manufacturer.name',
            'manufacturer.media.id',
            'manufacturer.media.url',
            'categoriesRo.id',
            'visibilities.id',
            'visibilities.salesChannelId',
            'visibilities.visibility',
            'media.id',
            'media.position',
            'media.media.id',
            'media.media.url',
        ]);
        $criteria->addAssociation('children');
        $childrenCriteria = $criteria->getAssociation('children');

        if (!$this->configProvider->isEnabledSyncInactiveProducts(
            $context->getSalesChannelId(),
            $context->getLanguageId(),
        )) {
            $childrenCriteria->addFilter(new EqualsFilter('active', true));
        }

        $categoryBlocklist = $this->configProvider->getCategoryBlocklist(
            $context->getSalesChannelId(),
            $context->getLanguageId(),
        );
        if (count($categoryBlocklist)) {
            $childrenCriteria->addFilter(
                new NotFilter(
                    NotFilter::CONNECTION_AND,
                    [new EqualsAnyFilter('categoriesRo.id', $categoryBlocklist)],
                ),
            );
        }

        $criteria->addFields([
            'children.id',
            'children.parentId',
            'children.productNumber',
            'children.active',
            'children.displayGroup',
            'children.isCloseout',
            'children.availableStock',
            'children.stock',
            'children.releaseDate',
            'children.manufacturerNumber',
            'children.ean',
            'children.shippingFree',
            'children.customSearchKeywords',
            'children.variantListingConfig',
            'children.price',
            'children.prices',
            'children.name',
            'children.customFields',
            'children.optionIds',
            'children.propertyIds',
        ]);
        $criteria->addFields([
            'children.cover.id',
            'children.cover.media.id',
            'children.cover.media.url',
            'children.manufacturer.id',
            'children.manufacturer.name',
            'children.manufacturer.media.id',
            'children.manufacturer.media.url',
            'children.visibilities.id',
            'children.visibilities.salesChannelId',
            'children.visibilities.visibility',
        ]);

        /** @var PartialProduct $mainProduct */
        $mainProduct = $this->productRepository
            ->search($criteria, $context->getContext())
            ->first();
        if ($mainProduct instanceof PartialEntity && !$mainProduct instanceof PartialProduct) {
            $mainProduct = PartialProductConverter::toPartialProduct($mainProduct);
        }

        /** @var PartialProduct $variantFromDb */
        $criteria = NostoCriteriaFactory::createWithIds([$variantId]);
        $criteria->addFields([
            'id',
            'parentId',
            'productNumber',
            'active',
            'childCount',
            'displayGroup',
            'isCloseout',
            'availableStock',
            'stock',
            'createdAt',
            'releaseDate',
            'ratingAverage',
            'variantListingConfig',
            'manufacturerNumber',
            'ean',
            'purchaseUnit',
            'referenceUnit',
            'shippingFree',
            'customSearchKeywords',
            'tagIds',
            'streamIds',
            'optionIds',
            'propertyIds',
            'unitId',
            'taxId',
            'price',
            'prices',
            'name',
            'description',
            'customFields',
            'keywords',
            'packUnit',
            'packUnitPlural',
            'metaTitle',
            'metaDescription',
        ]);

        $criteria->addFields([
            'cover.id',
            'cover.media.id',
            'cover.media.url',
            'manufacturer.id',
            'manufacturer.name',
            'manufacturer.media.id',
            'manufacturer.media.url',
            'categoriesRo.id',
            'visibilities.id',
            'visibilities.salesChannelId',
            'visibilities.visibility',
            'media.id',
            'media.position',
            'media.media.id',
            'media.media.url',
        ]);

        $variantFromDb = $this->productRepository
            ->search($criteria, $context->getContext())
            ->first();
        if ($variantFromDb instanceof PartialEntity && !$variantFromDb instanceof PartialProduct) {
            $variantFromDb = PartialProductConverter::toPartialProduct($variantFromDb);
        }

        $productTaggingHelper = new ProductTaggingHelper(
            $this->systemConfigService,
            $this->configProvider,
            $this->partialProductProvider,
            $this->productHelper,
        );

        return $productTaggingHelper->findProductId($context, $mainProduct, $variantFromDb, $isProductTagging);
    }

    public function getPageType($activeRoute, $pageCmsType): string
    {
        $pageType = 'notfound';

        if (empty($activeRoute)) {
            return $pageType;
        }

        switch ($activeRoute) {
            case 'frontend.home.page':
                $pageType = 'front';
                break;
            case 'frontend.navigation.page':
                if ($pageCmsType == 'product_list') {
                    $pageType = 'category';
                } else {
                    $pageType = 'other';
                }
                break;
            case 'frontend.detail.page':
                $pageType = 'product';
                break;
            case 'frontend.checkout.cart.page':
                $pageType = 'cart';
                break;
            case 'frontend.checkout.register.page':
            case 'frontend.checkout.confirm.page':
                $pageType = 'checkout';
                break;
            case 'frontend.checkout.finish.page':
                $pageType = 'order';
                break;
            case 'frontend.search.page':
                $pageType = 'search';
                break;
            default:
                $pageType = 'other';
        }

        return $pageType;
    }

    /**
     * @return array<string, mixed>
     */
    public function getNostoConfigurationValues(SalesChannelContext $context): array
    {
        $languageId = $context->getSalesChannel()->getLanguageId();
        $salesChannelId = $context->getSalesChannel()->getId();

        $config = $this->nostoConfigService->getConfig($salesChannelId, $languageId);

        if (!isset($config['productIdentifier'])) {
            $config = $this->nostoConfigService->getConfig();
        }

        return $config;
    }

    public function getNostoCategory(?CategoryEntity $category, SalesChannelContext $context): ?NostoCategory
    {
        try {
            $categoryId = $category->getId();

            $criteria = NostoCriteriaFactory::createWithIds([$categoryId]);
            $criteria->addAssociation('seoUrls');

            $categoryWithSeo = $this->categoryRepository
                ->search($criteria, $context->getContext())
                ->get($categoryId);

            return $this->nostoCategoryBuilder->build($categoryWithSeo, $context);
        } catch (Throwable $throwable) {
            $this->logger->error(
                $throwable->getMessage(),
                ContextHelper::createContextFromException($throwable),
            );

            return null;
        }
    }
}
