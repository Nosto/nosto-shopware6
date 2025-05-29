<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Controller\Storefront;

use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Nosto\Model\Signup\Account as NostoSignupAccount;
use Nosto\NostoIntegration\Model\MockOperation\MockUpsertProduct;
use Nosto\Request\Api\Token as NostoToken;

class NostoMonitoringAuthService
{
    private const SESSION_AUTH_FLAG = 'authenticatedWithNosto';
    private const SESSION_AUTH_TIMESTAMP = 'nostoAuthTimestamp';
    private const AUTH_TIMEOUT = 3600; // 1 hour

    public function validateAccessKey(string $accessKey): bool
    {
        return $this->validateWithNostoApi($accessKey);
    }

    public function authenticate(SessionInterface $session): void
    {
        $session->set(self::SESSION_AUTH_FLAG, true);
        $session->set(self::SESSION_AUTH_TIMESTAMP, time());
    }

    public function logout(SessionInterface $session): void
    {
        $session->remove(self::SESSION_AUTH_FLAG);
        $session->remove(self::SESSION_AUTH_TIMESTAMP);
    }

    public function isAuthenticated(SessionInterface $session): bool
    {
        if (!$session->get(self::SESSION_AUTH_FLAG)) {
            return false;
        }

        $authTimestamp = $session->get(self::SESSION_AUTH_TIMESTAMP);
        if (!$authTimestamp || (time() - $authTimestamp) > self::AUTH_TIMEOUT) {
            $this->logout($session);
            return false;
        }

        return true;
    }

    private function validateWithNostoApi(string $accessKey): bool
    {
        $account = new NostoSignupAccount('placeholder'); //todo this works, should it work?
        $account->addApiToken(new NostoToken(NostoToken::API_PRODUCTS, $accessKey));

        // TODO: Replace with actual Nosto API validation when available
        return (new MockUpsertProduct($account))->upsert()['success'];
    }
}