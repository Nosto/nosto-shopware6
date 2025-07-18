<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration;

use Composer\Script\Event;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class ShopwareScripts
{
    public static function postUpdate(Event $event): void
    {
        $event->getIO()->write("<info>Running post-update tasks...</info>");
        self::runShopwareTasks($event);
    }

    private static function runShopwareTasks(Event $event): void
    {
        $rootPath = self::findShopwareRootPath();

        if (!$rootPath) {
            $event->getIO()->writeError("Could not determine Shopware root path.");
            return;
        }

        $commands = [
            [$rootPath . '/bin/console', 'database:migrate', 'NostoIntegration', '--all'],
            [$rootPath . '/bin/build-administration.sh'],
            [$rootPath . '/bin/build-storefront.sh'],
            [$rootPath . '/bin/console', 'cache:clear'],
        ];

        foreach ($commands as $command) {
            $event->getIO()->write("Running: " . implode(' ', $command));
            $process = new Process($command, $rootPath);
            $process->setTimeout(300);

            try {
                $process->mustRun();
                $event->getIO()->write($process->getOutput());
            } catch (ProcessFailedException $e) {
                $event->getIO()->writeError("Failed: " . implode(' ', $command));
                $event->getIO()->writeError($e->getMessage());
            }
        }
    }

    private static function findShopwareRootPath(): ?string
    {
        $possiblePaths = [
            __DIR__ . '/../../../../composer.json',
            __DIR__ . '/../../../../../composer.json',
            __DIR__ . '/../../../../../../composer.json',
            dirname(__FILE__, 6) . '/composer.json',
            $_SERVER['DOCUMENT_ROOT'] . '/../composer.json',
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $composerJson = json_decode(file_get_contents($path), true);

                if (isset($composerJson['require']['shopware/core'])) {
                    return dirname($path);
                }
            }
        }

        return null;
    }
}
