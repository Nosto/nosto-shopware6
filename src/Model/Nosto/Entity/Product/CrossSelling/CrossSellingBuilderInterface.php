<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Entity\Product\CrossSelling;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface CrossSellingBuilderInterface
{
    public function build(string $productId, SalesChannelContext $context): array;
}
