<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Service\ScheduledTask;

use Nosto\Helper\VersionInfoHelper;
use Nosto\Model\Signup\Account;
use Nosto\NostoIntegration\Model\Nosto\Account\Provider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\NostoMonitoringHelper;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\Kernel;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: SendVersionInfoTask::class)]
class SendVersionInfoTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        private readonly LoggerInterface $logger,
        private readonly Provider $nostoAccountProvider,
        private readonly NostoMonitoringHelper $nostoMonitoringHelper
    ) {
        parent::__construct($scheduledTaskRepository);
    }

    public function run(): void
    {
        $this->logger->info('Starting Nosto version info send task');

        try {
            $nostoAccount = $this->getNostoAccount();

            if (!$nostoAccount) {
                $this->logger->warning('No Nosto account configured, skipping version info send');
                return;
            }

            // Get platform version
            $platformVersion = $this->getShopwarePlatformVersion();

            // Get plugin version (implement this based on your plugin)
            $pluginVersion = $this->nostoMonitoringHelper->getPluginVersion();

            // Send version info using our SDK
            $result = VersionInfoHelper::sendVersionInfo(
                $nostoAccount,
                'Shopware',
                $platformVersion,
                $pluginVersion,
                $this->logger
            );

            if ($result) {
                $this->logger->info('Successfully sent version info to Nosto via scheduled task', [
                    'platform' => 'Shopware',
                    'platformVersion' => $platformVersion,
                    'pluginVersion' => $pluginVersion
                ]);
            }

        } catch (\Exception $e) {
            $this->logger->error('Failed to send version info to Nosto via scheduled task', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    private function getNostoAccount(): ?Account
    {
        // Get the first available account from all configured accounts
        $context = new Context(new SystemSource());
        $accounts = $this->nostoAccountProvider->all($context);

        if (empty($accounts)) {
            return null;
        }

        // TODO: Check this.
        // Return the first account's Nosto account
        return $accounts[0]->getNostoAccount();
    }

    private function getShopwarePlatformVersion(): string
    {
        $debugPaths = [];

        // Try multiple possible paths for main Shopware composer.json
        $possiblePaths = [
            __DIR__ . '/../../../../composer.json',
            __DIR__ . '/../../../../../composer.json',
            __DIR__ . '/../../../../../../composer.json',
            dirname(__FILE__, 6) . '/composer.json',
            $_SERVER['DOCUMENT_ROOT'] . '/../composer.json',
        ];

        foreach ($possiblePaths as $path) {
            $debugPaths[] = $path . ' - ' . (file_exists($path) ? 'EXISTS' : 'NOT FOUND');

            if (file_exists($path)) {
                $composerJson = json_decode(file_get_contents($path), true);

                // Check if this looks like main Shopware composer.json
                if (isset($composerJson['require']['shopware/core'])) {
                    $version = $composerJson['require']['shopware/core'];
                    // Remove version constraints like ^, ~, >=, etc.
                    $version = preg_replace('/^[^\d]*/', '', $version);
                    if (!empty($version)) {
                        return $version;
                    }
                }
            }
        }

        // Fallback.
        if (defined('\Shopware\Core\Kernel::SHOPWARE_FALLBACK_VERSION')) {
            return Kernel::SHOPWARE_FALLBACK_VERSION;
        }

        if (defined('\Shopware\Core\Kernel::VERSION')) {
            return Kernel::VERSION;
        }

        return 'unknown';
    }
}