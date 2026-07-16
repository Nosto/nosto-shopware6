<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Enums;

use Nosto\NostoIntegration\Enums\CustomerDataMode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CustomerDataModeTest extends TestCase
{
    #[DataProvider('sendProvider')]
    public function testShouldSendCustomerData(
        CustomerDataMode $mode,
        bool $nostoTrackCookiePresent,
        bool $expectedSend,
    ): void {
        self::assertSame($expectedSend, $mode->shouldSendCustomerData($nostoTrackCookiePresent));
    }

    /**
     * @return array<string, array{0: CustomerDataMode, 1: bool, 2: bool}>
     */
    public static function sendProvider(): array
    {
        return [
            'always sends regardless of cookie (no cookie)' => [CustomerDataMode::ALWAYS, false, true],
            'always sends regardless of cookie (cookie)' => [CustomerDataMode::ALWAYS, true, true],
            'never suppresses regardless of cookie (no cookie)' => [CustomerDataMode::NEVER, false, false],
            'never suppresses regardless of cookie (cookie)' => [CustomerDataMode::NEVER, true, false],
            'rely-on-cookie sends only with cookie (no cookie)' => [CustomerDataMode::RELY_ON_COOKIE, false, false],
            'rely-on-cookie sends only with cookie (cookie)' => [CustomerDataMode::RELY_ON_COOKIE, true, true],
        ];
    }
}
