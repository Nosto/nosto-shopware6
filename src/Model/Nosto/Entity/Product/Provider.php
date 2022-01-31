<?php declare(strict_types=1);

namespace Od\NostoIntegration\Model\Nosto\Entity\Product;

use Nosto\Model\Product\Product as NostoProduct;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class Provider implements ProductProviderInterface
{
    private Builder $builder;

    public function __construct(Builder $builder)
    {
        $this->builder = $builder;
    }

    public function get(SalesChannelProductEntity $product, SalesChannelContext $context): NostoProduct
    {
        try {
            $nostoProduct = $this->builder->build($product, $context);
        } catch (\Exception $e) {
            throw new \Exception('Unable to build product, reason: ' . $e->getMessage());
        }

        return $nostoProduct;
    }
}
