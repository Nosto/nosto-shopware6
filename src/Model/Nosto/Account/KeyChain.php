<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Account;

use Nosto\Request\Api\Token;

class KeyChain
{
    /**
     * @param Token[] $tokens
     */
    public function __construct(
        private readonly array $tokens,
    ) {
    }

    /**
     * @return Token[]
     */
    public function getTokens(): array
    {
        return $this->tokens;
    }
}
