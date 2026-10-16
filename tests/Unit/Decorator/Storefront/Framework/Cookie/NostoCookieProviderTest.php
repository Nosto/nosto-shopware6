<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Decorator\Storefront\Framework\Cookie;

use Nosto\NostoIntegration\Decorator\Storefront\Framework\Cookie\NostoCookieProvider;
use Nosto\NostoIntegration\Model\ConfigProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Framework\Cookie\CookieProviderInterface;

final class NostoCookieProviderTest extends TestCase
{
    public function testNotEssentialPutsLoadConsentInDedicatedRejectableGroup(): void
    {
        $groups = $this->buildGroups(treatAsEssential: false);

        $nostoGroup = $this->findGroup($groups, 'nosto-integration.cookie.group.title');
        self::assertNotNull($nostoGroup, 'A dedicated, rejectable Nosto group must exist.');
        self::assertTrue($this->groupHasCookie($nostoGroup, NostoCookieProvider::NOSTO_COOKIE_KEY));

        // Must NOT be forced into the always-on required group.
        self::assertFalse(
            $this->groupHasCookie(
                $this->findGroup($groups, 'cookie.groupRequired'),
                NostoCookieProvider::NOSTO_COOKIE_KEY,
            ),
        );

        // Marketing cookie always lives in the marketing group.
        self::assertTrue(
            $this->groupHasCookie(
                $this->findGroup($groups, 'cookie.groupMarketing'),
                NostoCookieProvider::NOSTO_TRACK_COOKIE_KEY,
            ),
        );
    }

    public function testEssentialPutsLoadConsentInRequiredGroup(): void
    {
        $groups = $this->buildGroups(treatAsEssential: true);

        self::assertTrue(
            $this->groupHasCookie(
                $this->findGroup($groups, 'cookie.groupRequired'),
                NostoCookieProvider::NOSTO_COOKIE_KEY,
            ),
        );
        self::assertNull(
            $this->findGroup($groups, 'nosto-integration.cookie.group.title'),
            'No separate Nosto group when treated as essential.',
        );
        self::assertTrue(
            $this->groupHasCookie(
                $this->findGroup($groups, 'cookie.groupMarketing'),
                NostoCookieProvider::NOSTO_TRACK_COOKIE_KEY,
            ),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildGroups(bool $treatAsEssential): array
    {
        $inner = $this->createMock(CookieProviderInterface::class);
        $inner->method('getCookieGroups')->willReturn([
            [
                'snippet_name' => 'cookie.groupRequired',
                'isRequired' => true,
                'entries' => [],
            ],
            [
                'snippet_name' => 'cookie.groupMarketing',
                'entries' => [],
            ],
        ]);

        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('isEnabledIgnoreCookieConsent')->willReturn($treatAsEssential);

        return (new NostoCookieProvider($inner, $configProvider))->getCookieGroups();
    }

    /**
     * @param array<int, mixed> $groups
     * @return array<string, mixed>|null
     */
    private function findGroup(array $groups, string $snippetName): ?array
    {
        foreach ($groups as $group) {
            if (is_array($group) && ($group['snippet_name'] ?? null) === $snippetName) {
                return $group;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed>|null $group
     */
    private function groupHasCookie(?array $group, string $cookieKey): bool
    {
        foreach ($group['entries'] ?? [] as $entry) {
            if (is_array($entry) && ($entry['cookie'] ?? null) === $cookieKey) {
                return true;
            }
        }

        return false;
    }
}
