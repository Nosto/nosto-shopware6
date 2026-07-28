<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Model;

use Nosto\NostoIntegration\Model\Config\NostoConfigService;
use Nosto\NostoIntegration\Model\ConfigProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigProviderIgnoreCookieConsentTest extends TestCase
{
    #[DataProvider('valueProvider')]
    public function testDefaultsToOnWhenUnset(mixed $storedValue, bool $expected): void
    {
        $configService = $this->createMock(NostoConfigService::class);
        $configService->method('get')
            ->with(NostoConfigService::ENABLE_IGNORE_COOKIE_CONSENT, null, null)
            ->willReturn($storedValue);

        self::assertSame($expected, (new ConfigProvider($configService))->isEnabledIgnoreCookieConsent());
    }

    /**
     * @return array<string, array{0: mixed, 1: bool}>
     */
    public static function valueProvider(): array
    {
        return [
            'unset defaults to essential (on)' => [null, true],
            'explicit true' => [true, true],
            'explicit false respected' => [false, false],
        ];
    }
}
