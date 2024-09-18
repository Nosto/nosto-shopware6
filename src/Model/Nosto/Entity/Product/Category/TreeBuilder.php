<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Entity\Product\Category;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;

class TreeBuilder
{
    public const NAME_WITH_ID_TEMPLATE = '%s (ID = %s)';

    public function fromCategoriesRo(CategoryCollection $categoriesRo): array
    {
        $categoryNameSets = $this->getCategoryNameSets($categoriesRo);
        $categorySeoUrlsSets = $this->getCategorySeoUrlsSets($categoriesRo);

        $nostoCategoryNames = array_map(function (array $nameSet) {
            return array_reduce(
                $nameSet,
                function (array $acc, $categoryName) {
                    $acc[] = end($acc) . '/' . $categoryName;

                    return $acc;
                },
                [],
            );
        }, $categoryNameSets);

        $nostoCategorySeoUrls = array_map(function (array $nameSet) {
            return array_reduce(
                $nameSet,
                function (array $acc, $categoryName) {
                    $acc[] = end($acc) . '/' . $categoryName;

                    return $acc;
                },
                [],
            );
        }, $categorySeoUrlsSets);

        return array_values(array_unique(array_merge(...array_values($nostoCategoryNames), ...array_values($nostoCategorySeoUrls))));
    }

    public function fromCategoriesRoWithId(CategoryCollection $categoriesRo): array
    {
        $categoryNameSets = $this->getCategoryNameSets($categoriesRo);
        $nostoCategoryNames = [];

        foreach ($categoryNameSets as $catNames) {
            $nostoCategoryNames[] = '/' . sprintf(
                self::NAME_WITH_ID_TEMPLATE,
                implode('/', $catNames),
                array_key_last($catNames),
            );
        }

        return $nostoCategoryNames;
    }

    private function getCategoryNameSets(CategoryCollection $categoriesRo): array
    {
        if ($categoriesRo->count() < 1) {
            return [];
        }

        $rootCategoryId = $categoriesRo
            ->filter(fn (CategoryEntity $category) => $category->getParentId() === null)
            ->first()->getId();

        return array_filter(array_map(function (CategoryEntity $category) use ($rootCategoryId) {
            return array_filter(
                $category->getPlainBreadcrumb(),
                fn (string $categoryId) => $categoryId !== $rootCategoryId,
                ARRAY_FILTER_USE_KEY,
            );
        }, $categoriesRo->getElements()));
    }

    private function getCategorySeoUrlsSets(CategoryCollection $categoriesRo): array
    {
        if ($categoriesRo->count() < 1) {
            return [];
        }

        $seoUrlPaths = [];

        foreach ($categoriesRo->getElements() as $category) {
            if (null === $category->getSeoUrls()) {
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
