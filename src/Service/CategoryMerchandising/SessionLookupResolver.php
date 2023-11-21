<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Service\CategoryMerchandising;

use Nosto\NostoException;
use Nosto\NostoIntegration\Model\Nosto\Account;
use Nosto\NostoIntegration\Model\Nosto\Account\Provider;
use Nosto\Operation\Session\NewSession;
use Nosto\Request\Http\Exception\{AbstractHttpException, HttpResponseException};
use Shopware\Core\Framework\Context;
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
    public function getSessionId(Context $context, string $salesChannelId, string $languageId): string
    {
        $request = $this->requestStack->getCurrentRequest();
        $customerId = $request->cookies->get(self::NOSTO_SESSION_COOKIE);
        $account = $this->getNostoAccount($context, $salesChannelId, $languageId);

        if ($account && !$customerId) {
            $session = new NewSession($account->getNostoAccount(), '', false);
            $customerId = $session->execute();
        }

        return $customerId;
    }

    public function getNostoAccount(Context $context, string $channelId, string $languageId): ?Account
    {
        return $this->accountProvider->get($context, $channelId, $languageId);
    }
}
