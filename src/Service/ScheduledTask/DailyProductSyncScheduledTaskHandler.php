<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Service\ScheduledTask;

use Nosto\NostoIntegration\Api\Route\NostoSyncRoute;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\CachedProvider;
use Nosto\NostoIntegration\Utils\Logger\ContextHelper;
use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\HttpFoundation\Request;

class DailyProductSyncScheduledTaskHandler extends ScheduledTaskHandler
{
    private const LAST_EXECUTION_TIME_CONFIG = 'NostoIntegration.config.dailySyncLastTime';

    private ConfigProvider $configProvider;

    private SystemConfigService $systemConfigService;

    private NostoSyncRoute $nostoSyncRoute;

    private TagAwareAdapterInterface $cache;

    private LoggerInterface $logger;

    public function __construct(
        EntityRepository $scheduledTaskRepository,
        ConfigProvider $configProvider,
        SystemConfigService $systemConfigService,
        NostoSyncRoute $nostoSyncRoute,
        TagAwareAdapterInterface $cache,
        LoggerInterface $logger
    ) {
        parent::__construct($scheduledTaskRepository);
        $this->configProvider = $configProvider;
        $this->systemConfigService = $systemConfigService;
        $this->nostoSyncRoute = $nostoSyncRoute;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    public static function getHandledMessages(): iterable
    {
        return [DailyProductSyncScheduledTask::class];
    }

    public function run(): void
    {
        if ($this->isTimeToRunJob()) {
            try {
                $this->cache->clear(CachedProvider::CACHE_PREFIX);
                // Here we have context-less process
                $this->nostoSyncRoute->fullCatalogSync(new Request(), new Context(new SystemSource()));
                $this->systemConfigService->set(
                    self::LAST_EXECUTION_TIME_CONFIG,
                    (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)
                );
            } catch (\Exception $e) {
                $this->logger->error(
                    sprintf(
                        'Unable to sync job, the reason is: %s',
                        $e->getMessage()
                    ),
                    ContextHelper::createContextFromException($e)
                );
            }
        }
    }

    private function isTimeToRunJob(?string $channelId = null): bool
    {
        if (!$this->configProvider->isDailyProductSyncEnabled($channelId) || $this->isTodayAlreadyRun($channelId)) {
            return false;
        }

        try {
            $executionTime = new \DateTime($this->configProvider->getDailyProductSyncTime($channelId));
        } catch (\Exception $e) {
            return false;
        }

        return $executionTime <= new \DateTime();
    }

    private function isTodayAlreadyRun(?string $channelId = null): bool
    {
        $lastSyncTime = $this->systemConfigService->get(
            self::LAST_EXECUTION_TIME_CONFIG,
            $channelId
        );

        if (empty($lastSyncTime)) {
            return false;
        }

        try {
            $lastSyncTimeObject = new \DateTime($lastSyncTime);
        } catch (\Exception $e) {
            return false;
        }

        return $lastSyncTimeObject->format(Defaults::STORAGE_DATE_FORMAT) === (new \DateTime())->format(
            Defaults::STORAGE_DATE_FORMAT
        );
    }
}
