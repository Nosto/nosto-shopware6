<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Service;

use Symfony\Component\HttpFoundation\Request;

class FilterPayloadService
{
    public function __construct(
        private readonly FilterPayloadStore $filterPayloadStore,
    ) {
    }

    public function resolveCookiePayload(Request $request, string $cookieName): ?string
    {
        $cookieValue = $request->cookies->get($cookieName);
        if (!$cookieValue) {
            return null;
        }

        return $this->filterPayloadStore->fetch($cookieValue);
    }
}
