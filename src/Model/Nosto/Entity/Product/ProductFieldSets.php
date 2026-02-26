<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Entity\Product;

final class ProductFieldSets
{
    public const BASE_FIELDS = [
        'id', 'parentId', 'productNumber', 'active', 'childCount',
        'displayGroup', 'isCloseout', 'availableStock', 'stock',
        'createdAt', 'releaseDate', 'ratingAverage', 'variantListingConfig',
        'manufacturerNumber', 'ean', 'purchaseUnit', 'referenceUnit',
        'shippingFree', 'customSearchKeywords', 'tagIds', 'streamIds',
        'optionIds', 'propertyIds', 'unitId', 'taxId',
        'price', 'prices', 'name', 'description', 'customFields',
        'keywords', 'packUnit', 'packUnitPlural', 'metaTitle', 'metaDescription',
    ];

    public const ASSOCIATION_FIELDS = [
        'cover.id', 'cover.media.id', 'cover.media.url',
        'manufacturer.id', 'manufacturer.name', 'manufacturer.media.id', 'manufacturer.media.url',
        'categoriesRo.id',
        'visibilities.id', 'visibilities.salesChannelId', 'visibilities.visibility',
        'media.id', 'media.position', 'media.media.id', 'media.media.url',
    ];

    public const CHILDREN_FIELDS = [
        'children.id', 'children.parentId', 'children.productNumber', 'children.active', 'children.displayGroup',
        'children.isCloseout', 'children.availableStock', 'children.stock', 'children.releaseDate',
        'children.manufacturerNumber', 'children.ean', 'children.shippingFree', 'children.customSearchKeywords',
        'children.variantListingConfig', 'children.price', 'children.prices', 'children.name',
        'children.customFields', 'children.optionIds', 'children.propertyIds',
        'children.cover.id', 'children.cover.media.id', 'children.cover.media.url',
        'children.manufacturer.id', 'children.manufacturer.name',
        'children.manufacturer.media.id', 'children.manufacturer.media.url',
        'children.visibilities.id', 'children.visibilities.salesChannelId', 'children.visibilities.visibility',
    ];

    public const PARENT_SYNC_FIELDS = [
        'id', 'parentId', 'productNumber', 'active', 'childCount',
        'displayGroup', 'isCloseout', 'availableStock', 'stock', 'variantListingConfig',
    ];

    public const COMMON_CHILDREN_FIELDS = [
        'children.id', 'children.parentId', 'children.active', 'children.isCloseout',
        'children.availableStock', 'children.stock', 'children.displayGroup',
    ];

    /**
     * @return array<int, string>
     */
    public static function productFields(): array
    {
        return array_merge(
            self::BASE_FIELDS,
            self::ASSOCIATION_FIELDS,
        );
    }

    /**
     * @return array<int, string>
     */
    public static function productFieldsWithChildren(): array
    {
        return array_merge(
            self::BASE_FIELDS,
            self::ASSOCIATION_FIELDS,
            self::CHILDREN_FIELDS,
        );
    }
}
