<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Entity\Product;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;

class PartialProductConverter
{
    public static function toPartialProduct(PartialEntity|PartialProduct $product): PartialProduct
    {
        if ($product instanceof PartialProduct) {
            return $product;
        }

        return new PartialProduct($product);
    }

    public static function toPartialProductCollection(EntityCollection $products): PartialProductCollection
    {
        $result = new PartialProductCollection();

        foreach ($products as $product) {
            if ($product instanceof PartialProduct) {
                $result->add($product);
                continue;
            }

            if ($product instanceof PartialEntity) {
                $result->add(self::toPartialProduct($product));
            }
        }

        return $result;
    }
}
