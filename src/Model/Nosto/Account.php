<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto;

use Nosto\Model\Signup\Account as NostoSignupAccount;

class Account
{
    private string $channelId;

    private string $languageId;

    private string $accountName;

    private Account\KeyChain $keyChain;

    private ?NostoSignupAccount $nostoAccount = null;

    public function __construct(
        string $channelId,
        string $languageId,
        string $accountName,
        Account\KeyChain $keyChain
    ) {
        $this->channelId = $channelId;
        $this->languageId = $languageId;
        $this->accountName = $accountName;
        $this->keyChain = $keyChain;
    }

    public function getChannelId(): string
    {
        return $this->channelId;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function getKeyChain(): Account\KeyChain
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
