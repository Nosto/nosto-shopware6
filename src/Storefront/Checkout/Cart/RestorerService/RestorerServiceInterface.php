<?php

namespace Nosto\NostoIntegration\Storefront\Checkout\Cart\RestorerService;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface RestorerServiceInterface
{
    public function restore(string $mappingId, SalesChannelContext $context): void;
}
