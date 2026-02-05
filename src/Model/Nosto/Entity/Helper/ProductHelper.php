<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Entity\Helper;

use Nosto\NostoIntegration\Enums\StockFieldOptions;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\Event\ProductLoadExistingCriteriaEvent;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\Event\ProductLoadExistingParentCriteriaEvent;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\Event\ProductReloadCriteriaEvent;
use Nosto\NostoIntegration\Search\Response\GraphQL\Filter\LabelTextFilter;
use Nosto\NostoIntegration\Search\Response\GraphQL\Filter\RangeSliderFilter;
use Nosto\NostoIntegration\Search\Response\GraphQL\Filter\Values\FilterValue;
use Nosto\NostoIntegration\Struct\FiltersExtension;
use Nosto\NostoIntegration\Struct\IdToFieldMapping;
use Nosto\NostoIntegration\Utils\NostoCriteriaFactory;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\Detail\AbstractProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\CountResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ProductHelper
{
    public function __construct(
        private readonly EntityRepository $productRepository,
        private readonly AbstractProductDetailRoute $productRoute,
        private readonly EntityRepository $reviewRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ConfigProvider $configProvider,
        private readonly SeoUrlPlaceholderHandlerInterface $seoUrlReplacer,
        private readonly SalesChannelRepository $salesChannelProductRepository,
        private readonly RouterInterface $router,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @var array<string, bool>
     */
    private array $loggingCache = [];

    public static function convertJsonToFilterMapping(?string $json): IdToFieldMapping
    {
        $mapping = new IdToFieldMapping();

        if (!$json) {
            return $mapping;
        }

        $decoded = json_decode($json, true);

        if (!is_array($decoded)) {
            return $mapping;
        }

        foreach ($decoded as $filterId => $field) {
            $mapping->addMapping($filterId, $field);
        }

        return $mapping;
    }

    public static function convertJsonToFilter(string $filters): FiltersExtension
    {
        $filtersExtension = new FiltersExtension();

        $jsonString = $filters;
        if (function_exists('gzuncompress')) {
            $compressed = base64_decode($filters);
            $jsonString = gzuncompress($compressed);
        }

        $parsed = json_decode($jsonString, true);

        $facets = $parsed['data']['search']['products']['facets'] ?? [];

        foreach ($facets as $facet) {
            $id = $facet['id'];
            $name = $facet['name'];
            $field = $facet['field'];
            $type = $facet['type'];

            if ($type === 'stats') {
                $min = $facet['min'] ?? 0;
                $max = $facet['max'] ?? 0;

                $filter = new RangeSliderFilter($id, $name, $field, $min, $max);
            } elseif ($type === 'terms') {
                $filter = new LabelTextFilter($id, $name, $field);

                foreach ($facet['data'] as $item) {
                    $value = $item['value'];
                    $filterValue = new FilterValue($value, $value);
                    $filter->addValue($filterValue);
                }
            } else {
                continue;
            }

            $filtersExtension->addFilter($filter);
        }

        return $filtersExtension;
    }

    public function getReviewsCount(SalesChannelProductEntity $product, SalesChannelContext $context): int
    {
        $reviewCriteria = NostoCriteriaFactory::create('product_sync.productHelper.getReviewsCount');
        $reviewCriteria->addFilter(
            new MultiFilter(MultiFilter::CONNECTION_OR, [
                new EqualsFilter('product.id', $product->getId()),
                new EqualsFilter('product.parentId', $product->getId()),
            ]),
        );
        $reviewCriteria->addAggregation(new CountAggregation('review-count', 'id'));
        $aggregation = $this->reviewRepository->aggregate($reviewCriteria, $context->getContext())->get('review-count');

        return $aggregation instanceof CountResult ? $aggregation->getCount() : 0;
    }

    public function reloadProduct(string $productId, SalesChannelContext $context): ?SalesChannelProductEntity
    {
        $criteria = $this->getCommonCriteria();
        $criteria->addFilter(new EqualsFilter('id', $productId));
        $this->eventDispatcher->dispatch(new ProductReloadCriteriaEvent($criteria, $context));
        $shopwareProduct = $this->getShopwareProducts([$productId], $context, true);

        return $shopwareProduct->get($productId) ?? null;
    }

    private function getCommonCriteria(?string $title = null): Criteria
    {
        $criteria = NostoCriteriaFactory::create($title);
        $criteria->addAssociation('media');
        $criteria->addAssociation('cover');
        //$criteria->addAssociation('options');
        //$criteria->addAssociation('properties');
        $criteria->addAssociation('options.group');
        $criteria->addAssociation('properties.group');
        $criteria->addAssociation('manufacturer');
        $criteria->addAssociation('manufacturer.media');
        $criteria->addAssociation('categoriesRo');
        $criteria->addAssociation('visibilities');

        return $criteria;
    }

    private function getSyncCriteria(?string $title, SalesChannelContext $context): Criteria
    {
        $criteria = NostoCriteriaFactory::create($title);
        $criteria->addAssociation('cover');
        $criteria->addAssociation('manufacturer');
        $criteria->addAssociation('manufacturer.media');
        $criteria->addAssociation('categoriesRo');
        $criteria->addAssociation('visibilities');

        if ($this->configProvider->isEnabledAlternateImages(
            $context->getSalesChannelId(),
            $context->getLanguageId(),
        )) {
            $criteria->addAssociation('media');
        }

        if ($this->configProvider->isEnabledProductProperties(
            $context->getSalesChannelId(),
            $context->getLanguageId(),
        )) {
            $criteria->addAssociation('options.group');
            $criteria->addAssociation('properties.group');
        }

        return $criteria;
    }

    private function getCommonCriteriaChildren($criteria): void
    {
        $criteria->addAssociation('children');
        $childrenCriteria = $criteria->getAssociation('children');
        $childrenCriteria->addFields([
            'id',
            'parentId',
            'active',
            'isCloseout',
            'availableStock',
            'stock',
            'displayGroup',
        ]);
    }

    public function loadExistingParentProducts(
        array $existentParentProductIds,
        SalesChannelContext $context,
    ): RepositoryIterator {
        $shouldLog = $this->shouldLogExtra($context);
        $startedAt = $shouldLog ? microtime(true) : null;
        $salesChannelId = $context->getSalesChannelId();
        $languageId = $context->getLanguageId();

        $criteria = $this->getCommonCriteria('product_sync.productHelper.loadExistingParentProducts');
        $criteria->addAssociation('children');
        $criteria->setLimit(100);

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

        $criteria->addFilter(new EqualsAnyFilter('id', array_unique(array_values($existentParentProductIds))));
        $this->eventDispatcher->dispatch(new ProductLoadExistingParentCriteriaEvent($criteria, $context));

        $iterator = new RepositoryIterator($this->productRepository, $context->getContext(), $criteria);

        if ($shouldLog && $startedAt !== null) {
            $this->logDuration(
                $context,
                'product_sync.productHelper.loadExistingParentProducts.criteria',
                $startedAt,
                [
                    'parent_ids' => count($existentParentProductIds),
                ],
            );
        }

        return $iterator;
    }

    public function getProductsIterator(
        array $productIds,
        SalesChannelContext $context,
    ): RepositoryIterator {
        $salesChannelId = $context->getSalesChannelId();
        $languageId = $context->getLanguageId();

        $criteria = NostoCriteriaFactory::create('product_sync.productHelper.getProductsIterator');
        $criteria->setLimit(100);
        $criteria->addFilter(new EqualsAnyFilter('id', $productIds));

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

        $this->eventDispatcher->dispatch(new ProductLoadExistingCriteriaEvent($criteria, $context));

        return new RepositoryIterator($this->productRepository, $context->getContext(), $criteria);
    }

    /**
     * @return array<string, string>
     */
    public function loadOrderNumberMapping(array $ids, Context $context): array
    {
        $criteria = NostoCriteriaFactory::createWithIds($ids, 'product_sync.productHelper.loadOrderNumberMapping');
        $iterator = new RepositoryIterator($this->productRepository, $context, $criteria);
        $orderNumberMapping = [];
        while (($result = $iterator->fetch()) !== null) {
            foreach ($result as $product) {
                $orderNumberMapping[$product->getId()] = $product->getProductNumber();
            }
        }

        return $orderNumberMapping;
    }

    public function getProductUrl(ProductEntity $product, SalesChannelContext $context): ?string
    {
        if ($domains = $context->getSalesChannel()->getDomains()) {
            $domainId = (string) $this->configProvider->getDomainId(
                $context->getSalesChannelId(),
                $context->getLanguageId(),
            );
            $domain = $domains->has($domainId) ? $domains->get($domainId) : $domains->first();
            $raw = $this->seoUrlReplacer->generate('frontend.detail.page', [
                'productId' => $product->getId(),
            ]);
            return $this->seoUrlReplacer->replace($raw, $domain?->getUrl() ?? '', $context);
        }

        return null;
    }

    public function getProductUrlById(string $productId, SalesChannelContext $context): ?string
    {
        if ($domains = $context->getSalesChannel()->getDomains()) {
            $domainId = (string) $this->configProvider->getDomainId(
                $context->getSalesChannelId(),
                $context->getLanguageId(),
            );
            $domain = $domains->has($domainId) ? $domains->get($domainId) : $domains->first();
            $raw = $this->seoUrlReplacer->generate('frontend.detail.page', [
                'productId' => $productId,
            ]);
            return $this->seoUrlReplacer->replace($raw, $domain?->getUrl() ?? '', $context);
        }

        return null;
    }

    public function createRepositoryIterator(Criteria $criteria, Context $context): RepositoryIterator
    {
        return new RepositoryIterator($this->productRepository, $context, $criteria);
    }

    private function shouldLogExtra(SalesChannelContext $context): bool
    {
        $cacheKey = sprintf('%s-%s', $context->getSalesChannelId(), $context->getLanguageId());
        if (!array_key_exists($cacheKey, $this->loggingCache)) {
            $this->loggingCache[$cacheKey] = $this->configProvider->isEnabledProductSyncExtraLogging(
                $context->getSalesChannelId(),
                $context->getLanguageId(),
            );
        }

        return $this->loggingCache[$cacheKey];
    }

    private function logDuration(
        SalesChannelContext $context,
        string $message,
        float $startedAt,
        array $additionalContext = [],
    ): void {
        $durationMs = (microtime(true) - $startedAt) * 1000;
        $this->logger->info($message, array_merge(
            $additionalContext,
            [
                'duration_ms' => round($durationMs, 2),
                'sales_channel_id' => $context->getSalesChannelId(),
                'language_id' => $context->getLanguageId(),
            ],
        ));
    }

    public function getShopwareProducts(
        array $productIds,
        SalesChannelContext $context,
        bool $isProductTagging = false,
    ): SalesChannelProductCollection {
        $shouldLog = $this->shouldLogExtra($context);
        $startedAt = $shouldLog ? microtime(true) : null;
        $criteria = $this->getSyncCriteria('product_sync.productHelper.getShopwareProducts', $context);
        if (!$isProductTagging) {
            $this->getCommonCriteriaChildren($criteria);
        }
        $criteria->setIds($productIds);

        $result = $this->salesChannelProductRepository->search(
            $criteria,
            $context,
        )->getEntities();

        if ($shouldLog && $startedAt !== null) {
            $this->logDuration(
                $context,
                'product_sync.productHelper.getShopwareProducts',
                $startedAt,
                [
                    'product_count' => count($productIds),
                ],
            );
        }

        return $result;
    }

    public function getShopwareProductsPartial(
        array $productIds,
        SalesChannelContext $context,
        bool $includeChildren = true,
    ): EntityCollection {
        $criteria = $this->getSyncPartialCriteria('product_sync.productHelper.getShopwareProducts.partial', $context);
        if ($includeChildren) {
            $this->addSyncPartialChildren($criteria, $context);
        }
        $criteria->setIds($productIds);

        return $this->salesChannelProductRepository->search(
            $criteria,
            $context,
        )->getEntities();
    }

    private function getSyncPartialCriteria(?string $title, SalesChannelContext $context): Criteria
    {
        $criteria = NostoCriteriaFactory::create($title);
        $criteria->addAssociation('cover');
        $criteria->addAssociation('manufacturer');
        $criteria->addAssociation('manufacturer.media');
        $criteria->addAssociation('categoriesRo');
        $criteria->addAssociation('visibilities');
        $criteria->addAssociation('options.group');
        $criteria->addAssociation('properties.group');
        $criteria->addAssociation('media');

//        $loadFull = $this->configProvider->isEnabledProductProperties(
//            $context->getSalesChannelId(),
//            $context->getLanguageId(),
//        );
//        if ($loadFull) {
//            return $criteria;
//        }

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

        return $criteria;
    }

    private function addSyncPartialChildren(Criteria $criteria, SalesChannelContext $context): void
    {
        $criteria->addAssociation('children');
        $childrenCriteria = $criteria->getAssociation('children');
        $childrenCriteria->addAssociation('cover');
        $childrenCriteria->addAssociation('manufacturer');
        $childrenCriteria->addAssociation('manufacturer.media');
        $childrenCriteria->addAssociation('visibilities');
        $childrenCriteria->addAssociation('options');
        $childrenCriteria->addAssociation('properties');
        $childrenCriteria->addAssociation('options.group');
        $childrenCriteria->addAssociation('properties.group');
//        if ($this->configProvider->isEnabledProductProperties(
//            $context->getSalesChannelId(),
//            $context->getLanguageId(),
//        )) {
//            return;
//        }
        $childrenCriteria->addFields([
            'id',
            'parentId',
            'productNumber',
            'active',
            'displayGroup',
            'isCloseout',
            'availableStock',
            'stock',
            'releaseDate',
            'manufacturerNumber',
            'ean',
            'shippingFree',
            'customSearchKeywords',
            'variantListingConfig',
            'price',
            'prices',
            'name',
            'customFields',
        ]);
        $childrenCriteria->addFields([
            'cover.id',
            'cover.media.id',
            'cover.media.url',
            'manufacturer.id',
            'manufacturer.name',
            'manufacturer.media.id',
            'manufacturer.media.url',
            'visibilities.id',
            'visibilities.salesChannelId',
            'visibilities.visibility',
        ]);
    }

    protected function buildFallbackImage(SalesChannelContext $context, RequestContext $requestContext): string
    {
        $configuredUrl = $this->configProvider->getFallbackImageUrl(
            $context->getSalesChannelId(),
            $context->getLanguageId(),
        );

        if ($configuredUrl) {
            return $configuredUrl;
        }

        $schemaAuthority = null;

        if ($domains = $context->getSalesChannel()->getDomains()) {
            $domainId = (string) $this->configProvider->getDomainId(
                $context->getSalesChannelId(),
                $context->getLanguageId(),
            );

            if ($domainId && $domains->has($domainId)) {
                $domain = $domains->get($domainId);
                $schemaAuthority = $domain?->getUrl();
            }
        }

        if (!$schemaAuthority) {
            $schemaAuthority = $requestContext->getScheme() . '://' . $requestContext->getHost();
            if ($requestContext->getHttpPort() !== 80) {
                $schemaAuthority .= ':' . $requestContext->getHttpPort();
            } elseif ($requestContext->getHttpsPort() !== 443) {
                $schemaAuthority .= ':' . $requestContext->getHttpsPort();
            }
        }

        return sprintf(
            '%s/%s',
            $schemaAuthority,
            'bundles/storefront/assets/icon/default/placeholder.svg',
        );
    }

    public function getFallbackImageUrl(SalesChannelContext $context): string
    {
        return $this->buildFallbackImage($context, $this->router->getContext());
    }

    public function getProductStock(
        ProductEntity|SalesChannelProductEntity $product,
        SalesChannelContext $context,
    ): int {
        return $this->configProvider->getStockField(
            $context->getSalesChannelId(),
            $context->getLanguageId(),
        ) === StockFieldOptions::ACTUAL_STOCK
            ? $product->getStock()
            : $product->getAvailableStock();
    }

    /**
     * @return array<string, mixed>
     */
    public function preparePropertiesOrOptions(PropertyGroupOptionCollection $array): array
    {
        $properties = [];

        foreach ($array as $property) {
            $group = $property->getGroup();
            if (!$group) {
                continue;
            }

            $groupName = $this->getEntityName($group);
            $propertyName = $this->getEntityName($property);
            if (!$groupName || !$propertyName) {
                continue;
            }

            $properties[$groupName] = isset($properties[$groupName])
                ? $properties[$groupName] . ', ' . $propertyName
                : $propertyName;
        }

        return $properties;
    }

    /**
     * @param iterable<object|array> $items
     * @return array<string, mixed>
     */
    public function preparePropertiesOrOptionsGeneric(iterable $items): array
    {
        $properties = [];

        foreach ($items as $property) {
            $group = null;
            if (is_object($property) && method_exists($property, 'getGroup')) {
                $group = $property->getGroup();
            } elseif (is_object($property) && method_exists($property, 'get')) {
                $group = $property->get('group');
            } elseif (is_array($property)) {
                $group = $property['group'] ?? null;
            }

            if (!$group) {
                continue;
            }

            $groupName = $this->getEntityName($group);
            $propertyName = $this->getEntityName($property);
            if (!$groupName || !$propertyName) {
                continue;
            }

            $properties[$groupName] = isset($properties[$groupName])
                ? $properties[$groupName] . ', ' . $propertyName
                : $propertyName;
        }

        return $properties;
    }

    private function getEntityName(object|array $entity): ?string
    {
        if (method_exists($entity, 'getTranslation')) {
            $translated = $entity->getTranslation('name');
            if (!empty($translated)) {
                return $translated;
            }
        }
        if (method_exists($entity, 'getName')) {
            $name = $entity->getName();
            if (!empty($name)) {
                return $name;
            }
        }
        if (method_exists($entity, 'get')) {
            $name = $entity->get('name');
            if (!empty($name)) {
                return $name;
            }
        }
        if (is_array($entity)) {
            $translated = $entity['translated']['name'] ?? null;
            if (!empty($translated)) {
                return $translated;
            }
            $name = $entity['name'] ?? null;
            if (!empty($name)) {
                return $name;
            }
        }
        return null;
    }
}
