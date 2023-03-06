<?php

namespace Od\NostoIntegration\Utils\RequestHelper;

use Shopware\Core\Framework\Context;

interface RequestHelperInterface
{
    public function initUserAgent(Context $context): void;

}