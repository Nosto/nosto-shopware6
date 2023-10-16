<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Entity\Product;

use Nosto\Model\Product\Sku as NostoSku;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface SkuBuilderInterface
{
    public function build(ProductEntity $product, SalesChannelContext $context): NostoSku;
}
