<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Model;

use Nosto\NostoIntegration\Model\Config\NostoConfigService;
use Nosto\NostoIntegration\Model\ConfigProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigProviderIndexedProductIdentifierSearchTest extends TestCase
{
    #[DataProvider('valueProvider')]
    public function testDefaultsToOffWhenUnset(mixed $storedValue, bool $expected): void
    {
        $configService = $this->createMock(NostoConfigService::class);
        $configService->method('getBool')
            ->with(NostoConfigService::ENABLE_INDEXED_PRODUCT_IDENTIFIER_SEARCH, null, null)
            ->willReturn($storedValue);

        self::assertSame(
            $expected,
            (new ConfigProvider($configService))->isIndexedProductIdentifierSearchEnabled(),
        );
    }

    /**
     * @return array<string, array{0: bool, 1: bool}>
     */
    public static function valueProvider(): array
    {
        return [
            'unset defaults to off' => [false, false],
            'explicit true' => [true, true],
        ];
    }
}
