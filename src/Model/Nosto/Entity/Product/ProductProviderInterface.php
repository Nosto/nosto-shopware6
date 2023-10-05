<?php declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Entity\Product;

use Nosto\Model\Product\Product as NostoProduct;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface ProductProviderInterface
{
    public function get(SalesChannelProductEntity $product, SalesChannelContext $context): NostoProduct;
}
