<?php declare(strict_types=1);

namespace Od\NostoIntegration\Model\Nosto\Entity\Product;

use Nosto\Model\Product\Product as NostoProduct;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface ProductProviderInterface
{
    public function get(ProductEntity $product, SalesChannelContext $context): NostoProduct;
}
