<?php

declare(strict_types=1);

namespace Od\NostoIntegration\Decorator\Storefront\Framework\Cookie;

use Shopware\Storefront\Framework\Cookie\CookieProviderInterface;

class NostoCookieProvider implements CookieProviderInterface
{
    private CookieProviderInterface $originalService;

    public function __construct(CookieProviderInterface $service)
    {
        $this->originalService = $service;
    }

    public function getCookieGroups(): array
    {
        return array_merge($this->originalService->getCookieGroups(), [
            [
                'snippet_name' => 'od-nosto.cookie.value',
                'snippet_description' => 'od-nosto.cookie.description',
                'cookie' => 'od-nosto-track-allow',
                'value' => true,
                'expiration' => '30',
            ],
        ]);
    }
}
