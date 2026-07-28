<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Decorator\Storefront\Framework\Cookie;

use Nosto\NostoIntegration\Model\ConfigProvider;
use Shopware\Storefront\Framework\Cookie\CookieProviderInterface;

class NostoCookieProvider implements CookieProviderInterface
{
    public const SHOPWARE_COOKIE_PREFERENCE_KEY = 'cookie-preference';

    public const LEGACY_COOKIE_KEY = 'od-nosto-track-allow';

    /**
     * Former name of NOSTO_COOKIE_KEY. Kept for backward compatibility so shoppers who
     * already consented (and thus have this cookie) are not forced to consent again.
     */
    public const LEGACY_TRACK_ALLOW_COOKIE_KEY = 'nosto-integration-track-allow';

    public const NOSTO_COOKIE_KEY = 'nosto-integration-allowed';

    public const NOSTO_TRACK_COOKIE_KEY = 'nosto-track';

    public const NOSTO_FILTERS_KEY = 'nostoCookieFilter';

    public const NOSTO_FILTERS_MAPPING_KEY = 'nostoCookieFilterMapping';

    public function __construct(
        private readonly CookieProviderInterface $originalService,
        private readonly ConfigProvider $configProvider,
    ) {
    }

    /**
     * @return array<string|int, mixed>
     */
    public function getCookieGroups(): array
    {
        $cookies = $this->originalService->getCookieGroups();

        // When Nosto is treated as essential, its load-consent lives in the always-on required
        // group (Nosto loads for everyone, with doNotTrack unless marketing is accepted).
        // Otherwise it lives in a dedicated, rejectable "Nosto" group so declining it means
        // Nosto is never loaded (no ev1 requests at all).
        $treatAsEssential = $this->configProvider->isEnabledIgnoreCookieConsent();

        foreach ($cookies as &$cookie) {
            if (!is_array($cookie) || !array_key_exists('entries', $cookie)) {
                continue;
            }

            if ($treatAsEssential && $this->isRequiredCookieGroup($cookie)) {
                $cookie['entries'][] = $this->essentialCookieEntry();

                continue;
            }

            if ($this->isMarketingCookieGroup($cookie)) {
                $cookie['entries'][] = $this->marketingCookieEntry();
            }
        }
        unset($cookie);

        if (!$treatAsEssential) {
            $cookies[] = [
                'snippet_name' => 'nosto-integration.cookie.group.title',
                'snippet_description' => 'nosto-integration.cookie.group.description',
                'entries' => [$this->essentialCookieEntry()],
            ];
        }

        return $cookies;
    }

    /**
     * @return array<string, string|int>
     */
    private function essentialCookieEntry(): array
    {
        return [
            'snippet_name' => 'nosto-integration.cookie.value',
            'snippet_description' => 'nosto-integration.cookie.description',
            'cookie' => self::NOSTO_COOKIE_KEY,
            'expiration' => '30',
            'value' => 1,
        ];
    }

    /**
     * @return array<string, string|int>
     */
    private function marketingCookieEntry(): array
    {
        return [
            'snippet_name' => 'nosto-integration.cookie.marketing.value',
            'snippet_description' => 'nosto-integration.cookie.marketing.description',
            'cookie' => self::NOSTO_TRACK_COOKIE_KEY,
            'expiration' => '30',
            'value' => 1,
        ];
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
