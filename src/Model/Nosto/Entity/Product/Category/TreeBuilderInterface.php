<?php

declare(strict_types=1);

namespace Od\NostoIntegration\Model\Nosto\Entity\Product\Category;

use Shopware\Core\Content\Category\CategoryCollection;

interface TreeBuilderInterface
{
    public function fromCategoriesRo(CategoryCollection $categoriesRo): array;
}
