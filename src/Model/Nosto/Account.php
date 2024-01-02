<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto;

use Nosto\Model\Signup\Account as NostoSignupAccount;
use Nosto\NostoIntegration\Model\Nosto\Account\KeyChain;

class Account
{
    private ?NostoSignupAccount $nostoAccount = null;

    public function __construct(
        private readonly string $channelId,
        private readonly string $languageId,
        private readonly string $accountName,
        private readonly KeyChain $keyChain,
    ) {
    }

    public function getChannelId(): string
    {
        return $this->channelId;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function getKeyChain(): KeyChain
    {
        return $this->keyChain;
    }

    public function getNostoAccount(): NostoSignupAccount
    {
        if ($this->nostoAccount !== null) {
            return $this->nostoAccount;
        }

        $account = new NostoSignupAccount($this->accountName);

        foreach ($this->keyChain->getTokens() as $token) {
            $account->addApiToken($token);
        }

        return $this->nostoAccount = $account;
    }
}
