<?php declare(strict_types=1);

namespace Od\NostoIntegration\Service\CategoryMerchandising;

use Od\NostoIntegration\Model\Nosto\Account\Provider;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SessionLookupResolver
{
    private Provider $accountProvider;

    public function __construct(Provider $accountProvider)
    {
        $this->accountProvider = $accountProvider;
    }

    public function getSessionData(SalesChannelContext $context): array
    {
        $data = [];
        $customerId = $context->getExtension('customerId')->getVars();

        $data['customerId'] = $customerId['id'];
        $data['account'] = $this->accountProvider->get($context->getSalesChannelId());

        return $data;
    }
}