<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Entity\Product\Category;

use Nosto\NostoIntegration\Model\ConfigProvider;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class TreeBuilder
{
    public const NAME_WITH_ID_TEMPLATE = '%s (ID = %s)';

    public function __construct(
        private readonly ConfigProvider $configProvider,
    ) {
    }

    /**
     * When category scoping is enabled for the sales channel, keeps only the categories that live under one of
     * its entry points (navigation, footer or service category), so paths from other channels' trees are not
     * synced. Returns the collection unchanged otherwise.
     */
    public function scopeToSalesChannel(
        CategoryCollection $categoriesRo,
        SalesChannelContext $context,
    ): CategoryCollection {
        $entryPointIds = $this->getScopedEntryPointIds($context);
        if (empty($entryPointIds)) {
            return $categoriesRo;
        }

        return $categoriesRo->filter(static function (CategoryEntity $category) use ($entryPointIds): bool {
            foreach ($entryPointIds as $entryPointId) {
                if ($category->getId() === $entryPointId
                    || str_contains((string) $category->getPath(), '|' . $entryPointId . '|')
                ) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * With category scoping enabled for the given context, each path starts below the entry point it belongs to.
     *
     * @return string[]
     */
    public function fromCategoriesRo(CategoryCollection $categoriesRo, ?SalesChannelContext $context = null): array
    {
        $categoryNameSets = $this->getCategoryNameSets($categoriesRo, $this->getScopedEntryPointIds($context));
        $categorySeoUrlsSets = $this->getCategorySeoUrlsSets($categoriesRo);

        $nostoCategoryNames = array_map(static fn (array $nameSet): mixed => array_reduce(
            $nameSet,
            static function (array $acc, $categoryName): array {
                if (!is_string($categoryName)) {
                    return $acc;
                }
                $acc[] = (end($acc) ?: '') . '/' . $categoryName;
                return $acc;
            },
            [],
        ), $categoryNameSets);

        $nostoCategorySeoUrls = array_map(function (array $nameSet) {
            return array_reduce(
                $nameSet,
                function (array $acc, $categoryName) {
                    if (!is_string($categoryName)) {
                        return $acc;
                    }
                    $acc[] = (end($acc) ?: '') . '/' . $categoryName;
                    return $acc;
                },
                [],
            );
        }, $categorySeoUrlsSets);

        return array_values(
            array_unique(array_merge(...array_values($nostoCategoryNames), ...array_values($nostoCategorySeoUrls))),
        );
    }

    /**
     * With category scoping enabled for the given context, each path starts below the entry point it belongs to.
     *
     * @return string[]
     */
    public function fromCategoriesRoWithId(
        CategoryCollection $categoriesRo,
        ?SalesChannelContext $context = null,
    ): array {
        $categoryNameSets = $this->getCategoryNameSets($categoriesRo, $this->getScopedEntryPointIds($context));
        $categorySeoUrlsSets = $this->getCategorySeoUrlsSets($categoriesRo);
        $nostoCategoryNames = [];
        $nostoCategorySeoUrls = [];

        foreach ($categoryNameSets as $catNames) {
            $nostoCategoryNames[] = '/' . sprintf(
                self::NAME_WITH_ID_TEMPLATE,
                implode('/', $catNames),
                array_key_last($catNames),
            );
        }

        foreach ($categorySeoUrlsSets as $key => $catNames) {
            $nostoCategorySeoUrls[] = '/' . sprintf(
                self::NAME_WITH_ID_TEMPLATE,
                $catNames[$key],
                $key,
            );
        }

        $merged = array_merge($nostoCategoryNames, $nostoCategorySeoUrls);

        $uniqueByName = [];

        foreach ($merged as $path) {
            $name = trim(explode('(ID =', $path)[0]);

            if (!isset($uniqueByName[$name])) {
                $uniqueByName[$name] = $path;
            }
        }

        return array_values($uniqueByName);
    }

    /**
     * @return string[] empty when no context is given or category scoping is disabled
     */
    private function getScopedEntryPointIds(?SalesChannelContext $context): array
    {
        if ($context === null || !$this->configProvider->isEnabledScopeCategoriesToSalesChannel(
            $context->getSalesChannelId(),
            $context->getLanguageId(),
        )) {
            return [];
        }

        $salesChannel = $context->getSalesChannel();

        return array_values(array_filter([
            $salesChannel->getNavigationCategoryId(),
            $salesChannel->getFooterCategoryId(),
            $salesChannel->getServiceCategoryId(),
        ]));
    }

    /**
     * @param string[] $entryPointIds
     *
     * @return string[][]
     */
    private function getCategoryNameSets(CategoryCollection $categoriesRo, array $entryPointIds = []): array
    {
        if ($categoriesRo->count() < 1) {
            return [];
        }

        if (!empty($entryPointIds)) {
            return array_filter(array_map(
                fn (CategoryEntity $category): array => $this->stripUpToEntryPoint(
                    $category->getPlainBreadcrumb(),
                    $entryPointIds,
                ),
                $categoriesRo->getElements(),
            ));
        }

        $rootCategoryId = $categoriesRo
            ->filter(static fn (CategoryEntity $category): bool => $category->getParentId() === null)
            ->first()?->getId();

        return array_filter(array_map(static fn (CategoryEntity $category): array => array_filter(
            $category->getPlainBreadcrumb(),
            static fn (string $categoryId): bool => $categoryId !== $rootCategoryId,
            ARRAY_FILTER_USE_KEY,
        ), $categoriesRo->getElements()));
    }

    /**
     * Drops the entry point and everything above it, so every tree of the sales channel
     * (navigation, footer, service) produces paths relative to its own entry point.
     *
     * @param array<string, string> $breadcrumb
     * @param string[] $entryPointIds
     *
     * @return array<string, string>
     */
    private function stripUpToEntryPoint(array $breadcrumb, array $entryPointIds): array
    {
        $position = 0;
        $offset = null;
        foreach (array_keys($breadcrumb) as $categoryId) {
            $position++;
            if (in_array($categoryId, $entryPointIds, true)) {
                $offset = $position;
            }
        }

        return $offset === null ? $breadcrumb : array_slice($breadcrumb, $offset, null, true);
    }

    /**
     * @return string[]
     */
    private function getCategorySeoUrlsSets(CategoryCollection $categoriesRo): array
    {
        if ($categoriesRo->count() < 1) {
            return [];
        }

        $seoUrlPaths = [];

        foreach ($categoriesRo->getElements() as $category) {
            if ($category->getSeoUrls() === null) {
                continue;
            }

            foreach ($category->getSeoUrls() as $seoUrlObject) {
                if (array_key_exists($seoUrlObject->getForeignKey(), $seoUrlPaths) && !$seoUrlObject->getIsModified()) {
                    continue;
                }

                if ($seoUrlObject->getIsCanonical()) {
                    $seoUrlPaths[$seoUrlObject->getForeignKey()] = [
                        $seoUrlObject->getForeignKey() => rtrim($seoUrlObject->getSeoPathInfo(), '/'),
                    ];
                }
            }
        }

        return $seoUrlPaths;
    }
}
