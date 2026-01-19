<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Service;

class FilterPayloadService
{
    private const TOKEN_PREFIX = 't:';

    public function __construct(
        private readonly FilterPayloadStore $filterPayloadStore,
    ) {
    }

    public function resolveCookiePayload(?string $cookieValue): ?string
    {
        if (!$cookieValue) {
            return null;
        }

        if (str_starts_with($cookieValue, self::TOKEN_PREFIX)) {
            $token = substr($cookieValue, strlen(self::TOKEN_PREFIX));
            return $this->filterPayloadStore->fetch($token);
        }

        return $cookieValue;
    }
}
