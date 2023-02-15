<?php

namespace Od\NostoIntegration\Model\Nosto\Entity\Product\CrossSelling;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface CrossSellingBuilderInterface
{
    public function build(string $productId, SalesChannelContext $context): array;
}
