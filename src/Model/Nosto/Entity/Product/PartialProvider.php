<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Entity\Product;

use Exception;
use Nosto\Model\Product\Product as NostoProduct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PartialProvider
{
    public function __construct(
        private readonly PartialBuilder $builder,
    ) {
    }

    public function get(object $product, SalesChannelContext $context): NostoProduct
    {
        try {
            $nostoProduct = $this->builder->build($product, $context);
        } catch (Exception $e) {
            throw new Exception('Unable to build product, reason: ' . $e->getMessage());
        }

        return $nostoProduct;
    }
}
