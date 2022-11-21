<?php declare(strict_types=1);

namespace Od\NostoIntegration\Service\CategoryMerchandising;

use Nosto\NostoException;
use Nosto\Operation\Session\NewSession;
use Nosto\Request\Http\Exception\{AbstractHttpException, HttpResponseException};
use Od\NostoIntegration\Model\Nosto\Account;
use Od\NostoIntegration\Model\Nosto\Account\Provider;
use Symfony\Component\HttpFoundation\RequestStack;

class SessionLookupResolver
{
    public const NOSTO_SESSION_COOKIE = '2c_cId';

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
        $customerId = $request->cookies->get(self::NOSTO_SESSION_COOKIE);
        $account = $this->getNostoAccount();

        if ($account && !$customerId) {
            $session = new NewSession($account->getNostoAccount(), '', false);
            $customerId = $session->execute();
        }

        return $customerId;
    }

    public function getNostoAccount(?string $channelId = null): ?Account
    {
        $request = $this->requestStack->getCurrentRequest();
        $channelId = $channelId === null ? $request->attributes->get('sw-sales-channel-id') : '';

        return $this->accountProvider->get($channelId);
    }
}
