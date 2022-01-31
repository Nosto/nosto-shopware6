<?php declare(strict_types=1);

namespace Od\NostoIntegration\Model\Nosto;

use Nosto\Model\Signup\Account as NostoSignupAccount;

class Account
{
    private string $channelId;
    private string $languageId;
    private Account\KeyChain $keyChain;
    private ?NostoSignupAccount $nostoAccount = null;

    public function __construct(
        string $channelId,
        string $languageId,
        Account\KeyChain $keyChain
    ) {
        $this->channelId = $channelId;
        $this->languageId = $languageId;
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

        // TODO: $accountName = $configProvider->getAccountname();
        $accountName = 'Shopware 6 Integration';
        $account = new NostoSignupAccount($accountName);

        foreach ($this->keyChain->getTokens() as $token) {
            $account->addApiToken($token);
        }

        return $this->nostoAccount = $account;
    }
}
