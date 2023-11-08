<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Decorator\Storefront\Framework\Cookie;

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
        $cookies = $this->originalService->getCookieGroups();

        foreach ($cookies as &$cookie) {
            if (!\is_array($cookie)) {
                continue;
            }

            if (!$this->isRequiredCookieGroup($cookie)) {
                continue;
            }

            if (!\array_key_exists('entries', $cookie)) {
                continue;
            }

            $cookie['entries'][] = [
                'snippet_name' => 'nosto-integration.cookie.value',
                'snippet_description' => 'nosto-integration.cookie.description',
                'cookie' => 'nosto-integration-track-allow',
                'expiration' => '30',
                'value' => 1,
            ];
        }

        return $cookies;
    }

    private function isRequiredCookieGroup(array $cookie): bool
    {
        return (\array_key_exists('isRequired', $cookie) && true === $cookie['isRequired'])
            && (\array_key_exists('snippet_name', $cookie) && 'cookie.groupRequired' === $cookie['snippet_name']);
    }
}
