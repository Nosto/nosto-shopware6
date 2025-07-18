<?php

namespace Nosto\NostoIntegration;

use Composer\Script\Event;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ShopwareScripts
{
    public static function postUpdate(Event $event)
    {
        $event->getIO()->write("<info>Running post-update tasks...</info>");
        self::runShopwareTasks($event);
    }

    private static function runShopwareTasks(Event $event)
    {
        $rootPath = realpath(__DIR__ . '/../../../../');
        $commands = [
            [$rootPath . '/bin/console', 'database:migrate', 'NostoIntegration', '--all'],
            [$rootPath . '/bin/build-administration.sh'],
            [$rootPath . '/bin/build-storefront.sh'],
            [$rootPath . '/bin/console', 'cache:clear'],
        ];

        foreach ($commands as $command) {
            $event->getIO()->write("👉 Running: " . implode(' ', $command));
            $process = new Process($command, $rootPath);
            $process->setTimeout(300);

            try {
                $process->mustRun();
                $event->getIO()->write($process->getOutput());
            } catch (ProcessFailedException $e) {
                $event->getIO()->writeError("❌ Failed: " . implode(' ', $command));
                $event->getIO()->writeError($e->getMessage());
            }
        }
    }
}
