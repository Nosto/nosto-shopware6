<?php

declare(strict_types=1);

namespace Od\NostoIntegration\Service\ScheduledTask;

use Od\NostoIntegration\Api\Route\OdNostoSyncRoute;
use Od\NostoIntegration\Model\ConfigProvider;
use Od\NostoIntegration\Model\Nosto\Entity\Product\CachedProvider;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\HttpFoundation\Request;

class DailyProductSyncScheduledTaskHandler extends ScheduledTaskHandler
{
    private const LAST_EXECUTION_TIME_CONFIG = 'NostoIntegration.settings.hidden.dailySyncLastTime';
    private ConfigProvider $configProvider;
    private SystemConfigService $systemConfigService;
    private OdNostoSyncRoute $nostoSyncRoute;
    private TagAwareAdapterInterface $cache;

    public function __construct(
        EntityRepositoryInterface $scheduledTaskRepository,
        ConfigProvider $configProvider,
        SystemConfigService $systemConfigService,
        OdNostoSyncRoute $nostoSyncRoute,
        TagAwareAdapterInterface $cache
    ) {
        parent::__construct($scheduledTaskRepository);
        $this->configProvider = $configProvider;
        $this->systemConfigService = $systemConfigService;
        $this->nostoSyncRoute = $nostoSyncRoute;
        $this->cache = $cache;
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
                $this->nostoSyncRoute->fullCatalogSync(new Request(), Context::createDefaultContext());
                $this->systemConfigService->set(
                    self::LAST_EXECUTION_TIME_CONFIG,
                    (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)
                );
            } catch (\Exception $e) {
                // leave it for next time
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
