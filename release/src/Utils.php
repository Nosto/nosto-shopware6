<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Release;

use Bramus\Monolog\Formatter\ColoredLineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

class Utils
{
    private const COMPOSER_JSON_DEST = __DIR__ . '/../../composer.json';

    public static function getComposerJsonData(): array
    {
        return json_decode(file_get_contents(self::COMPOSER_JSON_DEST), true);
    }

    public static function storeComposerJsonData(array $data): void
    {
        $json = static::toJsonString($data);

        file_put_contents(self::COMPOSER_JSON_DEST, $json);
    }

    public static function toJsonString(
        array $data,
        int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    ): string {
        return json_encode($data, $flags) . "\n";
    }

    public static function buildLogger(string $name): LoggerInterface
    {
        $logger = new Logger($name);
        $formatter = new ColoredLineFormatter(null, null, null, false, true);
        $logger->pushHandler((new StreamHandler('php://stdout'))->setFormatter($formatter));

        return $logger;
    }
}
