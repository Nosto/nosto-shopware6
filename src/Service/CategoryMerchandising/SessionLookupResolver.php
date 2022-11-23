<?php declare(strict_types=1);

namespace Od\NostoIntegration\Service\CategoryMerchandising;

use Nosto\NostoException;
use Nosto\Operation\Session\NewSession;
use Nosto\Request\Http\Exception\{AbstractHttpException, HttpResponseException};
use Od\NostoIntegration\Model\Nosto\Account;
use Od\NostoIntegration\Model\Nosto\Account\Provider;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\RequestStack;

class SessionLookupResolver
{
    private Provider $accountProvider;
    private RequestStack $requestStack;

    public function __construct(Provider $accountProvider, RequestStack $requestStack)
    {
        $this->accountProvider = $accountProvider;
        $this->requestStack = $requestStack;
    }

    /**
     * @throws HttpResponseException
     * @throws NostoException
     * @throws AbstractHttpException
     */
    public function getSessionId(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        $customerId = $request->cookies->get('2c_cId');
        $account = $this->getNostoAccount();

        if ($account && !$customerId) {
            $session = new NewSession($account->getNostoAccount(), '', false);
            $customerId = $session->execute();
        }

        return $customerId;
    }

    public function getNostoAccount(Context $context, ?string $channelId = null): ?Account
    {
        $request = $this->requestStack->getCurrentRequest();
        $channelId = $request->attributes->get('sw-sales-channel-id', '');

        return $this->accountProvider->get($context, $channelId);
    }
}
