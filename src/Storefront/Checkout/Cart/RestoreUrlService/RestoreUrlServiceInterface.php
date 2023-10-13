<?php

namespace Nosto\NostoIntegration\Storefront\Checkout\Cart\RestoreUrlService;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface RestoreUrlServiceInterface
{
    public function getCurrentRestoreUrl(SalesChannelContext $context): string;
}
