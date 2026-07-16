<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Decorator\Storefront\Framework\Cookie;

use Shopware\Storefront\Framework\Cookie\CookieProviderInterface;

class NostoCookieProvider implements CookieProviderInterface
{
    public const SHOPWARE_COOKIE_PREFERENCE_KEY = 'cookie-preference';

    public const LEGACY_COOKIE_KEY = 'od-nosto-track-allow';

    public const NOSTO_COOKIE_KEY = 'nosto-integration-track-allow';

    public const NOSTO_TRACK_COOKIE_KEY = 'nosto_track';

    public const NOSTO_FILTERS_KEY = 'nostoCookieFilter';

    public const NOSTO_FILTERS_MAPPING_KEY = 'nostoCookieFilterMapping';

    public function __construct(
        private readonly CookieProviderInterface $originalService,
    ) {
    }

    public function getCookieGroups(): array
    {
        $cookies = $this->originalService->getCookieGroups();

        foreach ($cookies as &$cookie) {
            if (!is_array($cookie)) {
                continue;
            }

            if (!array_key_exists('entries', $cookie)) {
                continue;
            }

            if ($this->isRequiredCookieGroup($cookie)) {
                $cookie['entries'][] = [
                    'snippet_name' => 'nosto-integration.cookie.value',
                    'snippet_description' => 'nosto-integration.cookie.description',
                    'cookie' => self::NOSTO_COOKIE_KEY,
                    'expiration' => '30',
                    'value' => 1,
                ];

                continue;
            }

            if ($this->isMarketingCookieGroup($cookie)) {
                $cookie['entries'][] = [
                    'snippet_name' => 'nosto-integration.cookie.marketing.value',
                    'snippet_description' => 'nosto-integration.cookie.marketing.description',
                    'cookie' => self::NOSTO_TRACK_COOKIE_KEY,
                    'expiration' => '30',
                    'value' => 1,
                ];
            }
        }

        return $cookies;
    }

    private function isRequiredCookieGroup(array $cookie): bool
    {
        return (array_key_exists('isRequired', $cookie) && $cookie['isRequired'] === true)
            && (array_key_exists('snippet_name', $cookie) && $cookie['snippet_name'] === 'cookie.groupRequired');
    }

    private function isMarketingCookieGroup(array $cookie): bool
    {
        return array_key_exists('snippet_name', $cookie) && $cookie['snippet_name'] === 'cookie.groupMarketing';
    }
}
