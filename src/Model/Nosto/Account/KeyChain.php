<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Account;

use Nosto\Request\Api\Token;

class KeyChain
{
    /**
     * @return Token[]
     */
    private array $tokens;

    public function __construct(array $tokens)
    {
        $this->tokens = $tokens;
    }

    public function getTokens(): array
    {
        return $this->tokens;
    }
}
