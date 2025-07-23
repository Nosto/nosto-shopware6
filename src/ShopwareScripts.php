<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration;

use Composer\Script\Event;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class ShopwareScripts
{
    private const PROCESS_TIMEOUT = 300;

    private const COMMANDS = [
        ['bin/console', 'database:migrate', 'NostoIntegration', '--all'],
        ['bin/build-administration.sh'],
        ['bin/build-storefront.sh'],
        ['bin/console', 'cache:clear'],
    ];

    public static function postInstall(Event $event): void
    {
        $event->getIO()->write("<info>Running post-install tasks...</info>");
        self::runShopwareTasks($event);
    }

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

        foreach (self::COMMANDS as $command) {
            $fullCommand = array_merge([$rootPath . '/' . $command[0]], array_slice($command, 1));
            self::executeCommand($fullCommand, $rootPath, $event);
        }
    }

    private static function executeCommand(array $command, string $workingDir, Event $event): void
    {
        $event->getIO()->write("Running: " . implode(' ', $command));
        $process = new Process($command, $workingDir);
        $process->setTimeout(self::PROCESS_TIMEOUT);

        try {
            $process->mustRun();
            $event->getIO()->write($process->getOutput());
        } catch (ProcessFailedException $e) {
            $event->getIO()->writeError("Failed: " . implode(' ', $command));
            $event->getIO()->writeError($e->getMessage());
        }
    }

    private static function findShopwareRootPath(): ?string
    {
        $possiblePaths = [
            __DIR__ . '/../../../../',
            __DIR__ . '/../../../../../',
            __DIR__ . '/../../../../../../',
            $_SERVER['DOCUMENT_ROOT'] . '/../',
            $_SERVER['DOCUMENT_ROOT'] . '/',
            getcwd() . '/../../../',
            getcwd() . '/../../',
        ];

        foreach ($possiblePaths as $path) {
            $normalizedPath = realpath($path);
            if ($normalizedPath && self::isShopwareRoot($normalizedPath)) {
                return $normalizedPath;
            }
        }

        return null;
    }

    private static function isShopwareRoot(string $path): bool
    {
        // Check for essential Shopware files/directories
        $requiredPaths = [
            $path . '/bin/console',
            $path . '/public',
        ];

        // At least one of these should exist
        $shopwarePaths = [
            $path . '/src/Core',
            $path . '/vendor/shopware/core',
            $path . '/config',
            $path . '/composer.json',
        ];

        // Check required paths
        foreach ($requiredPaths as $requiredPath) {
            if (!file_exists($requiredPath)) {
                return false;
            }
        }

        // Check if at least one Shopware-specific path exists
        foreach ($shopwarePaths as $shopwarePath) {
            if (file_exists($shopwarePath)) {
                return true;
            }
        }

        return false;
    }
}
