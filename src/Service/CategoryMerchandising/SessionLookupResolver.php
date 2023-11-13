<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Service\CategoryMerchandising;

use Nosto\NostoException;
use Nosto\NostoIntegration\Model\Nosto\Account;
use Nosto\NostoIntegration\Model\Nosto\Account\Provider;
use Nosto\Operation\Session\NewSession;
use Nosto\Request\Http\Exception\{AbstractHttpException, HttpResponseException};
use Shopware\Core\Framework\Context;
use Shopware\Core\PlatformRequest;
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
    public function getSessionId(Context $context): string
    {
        $request = $this->requestStack->getCurrentRequest();
        $customerId = $request->cookies->get(self::NOSTO_SESSION_COOKIE);
        $account = $this->getNostoAccount($context);

        if ($account && !$customerId) {
            $session = new NewSession($account->getNostoAccount(), '', false);
            $customerId = $session->execute();
        }

        return $customerId;
    }

    public function getNostoAccount(Context $context, ?string $channelId = null, ?string $languageId = null): ?Account
    {
        $request = $this->requestStack->getCurrentRequest();
        $channelId = $channelId ?? $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID);
        // TODO: fetch language id from request
        return $this->accountProvider->get($context, $channelId, $languageId);
    }
}
