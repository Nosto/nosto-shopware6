<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Model;

use Nosto\NostoIntegration\Enums\CustomerDataMode;
use Nosto\NostoIntegration\Model\Config\NostoConfigService;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Struct\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigProviderCustomerDataModeTest extends TestCase
{
    #[DataProvider('toArrayProvider')]
    public function testToArrayNormalizesCustomerDataToNostoToModeString(
        mixed $storedValue,
        string $expectedModeValue,
    ): void {
        $configService = $this->createMock(NostoConfigService::class);
        $configService->method('getConfigWithInheritance')
            ->willReturn([
                'customerDataToNosto' => $storedValue,
                'accountID' => 'acc',
            ]);

        $provider = new ConfigProvider($configService);
        $array = $provider->toArray();

        self::assertSame($expectedModeValue, $array['customerDataToNosto']);
        // The Struct has a ?string property; a raw bool would throw a TypeError here.
        $config = new Config($array);
        self::assertSame($expectedModeValue, $config->customerDataToNosto);
    }

    /**
     * @return array<string, array{0: mixed, 1: string}>
     */
    public static function toArrayProvider(): array
    {
        return [
            'legacy bool true -> always' => [true, 'always'],
            'legacy bool false -> never' => [false, 'never'],
            'string always kept' => ['always', 'always'],
            'unset -> rely-on-cookie' => [null, 'rely-on-cookie'],
        ];
    }

    #[DataProvider('modeProvider')]
    public function testGetCustomerDataModeResolvesStoredValue(
        mixed $storedValue,
        CustomerDataMode $expectedMode,
        bool $expectedBackendSend,
    ): void {
        $configService = $this->createMock(NostoConfigService::class);
        $configService->method('get')
            ->with(NostoConfigService::ENABLE_CUSTOMER_DATA_TO_NOSTO, null, null)
            ->willReturn($storedValue);

        $provider = new ConfigProvider($configService);

        self::assertSame($expectedMode, $provider->getCustomerDataMode());
        self::assertSame($expectedBackendSend, $provider->shouldSendCustomerDataFromBackend());
    }

    /**
     * @return array<string, array{0: mixed, 1: CustomerDataMode, 2: bool}>
     */
    public static function modeProvider(): array
    {
        return [
            'unset defaults to rely-on-cookie' => [null, CustomerDataMode::RELY_ON_COOKIE, false],
            'empty string defaults to rely-on-cookie' => ['', CustomerDataMode::RELY_ON_COOKIE, false],
            'legacy bool true maps to always' => [true, CustomerDataMode::ALWAYS, true],
            'legacy bool false maps to never' => [false, CustomerDataMode::NEVER, false],
            'string always' => ['always', CustomerDataMode::ALWAYS, true],
            'string never' => ['never', CustomerDataMode::NEVER, false],
            'string rely-on-cookie' => ['rely-on-cookie', CustomerDataMode::RELY_ON_COOKIE, false],
            'unknown string defaults to rely-on-cookie' => ['bogus', CustomerDataMode::RELY_ON_COOKIE, false],
        ];
    }
}
