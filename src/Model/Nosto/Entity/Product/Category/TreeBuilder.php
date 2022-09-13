<?php declare(strict_types=1);

namespace Od\NostoIntegration\Model\Nosto\Entity\Product\Category;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;

class TreeBuilder implements TreeBuilderInterface
{
    public function fromCategoriesRo(CategoryCollection $categoriesRo): array
    {
        $rootCategoryId = $categoriesRo
            ->filter(fn(CategoryEntity $category) => $category->getParentId() === null)
            ->first()->getId();

        $categoryNameSets = \array_filter(\array_map(function(CategoryEntity $category) use ($rootCategoryId) {
            return \array_filter(
                $category->getPlainBreadcrumb(),
                fn(string $categoryId) => $categoryId !== $rootCategoryId,
                ARRAY_FILTER_USE_KEY
            );
        }, $categoriesRo->getElements()));
        $nostoCategoryNames = \array_map(function (array $nameSet) {
            return array_reduce($nameSet,
                function (array $acc, $categoryName) {
                    $acc[] = (string) end($acc) . '/' . $categoryName;
                    return $acc;
                },
                []
            );
        }, $categoryNameSets);

        return \array_values(\array_unique(array_merge([], ...array_values($nostoCategoryNames))));
    }
}
