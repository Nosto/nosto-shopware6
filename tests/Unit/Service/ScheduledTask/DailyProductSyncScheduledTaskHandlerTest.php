<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Service\ScheduledTask;

use Nosto\NostoIntegration\Api\Route\NostoSyncRoute;
use Nosto\NostoIntegration\Model\Config\NostoConfigService;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\CachedProvider;
use Nosto\NostoIntegration\Service\ScheduledTask\DailyProductSyncScheduledTaskHandler;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\HttpFoundation\Request;

final class DailyProductSyncScheduledTaskHandlerTest extends TestCase
{
    public function testRunTriggersTheFullCatalogSyncOncePerDay(): void
    {
        $scheduledTaskRepository = $this->createMock(EntityRepository::class);
        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('isDailyProductSyncEnabled')->willReturn(true);
        $configProvider->method('getDailyProductSyncTime')->willReturn('2000-01-01 00:00:00');

        $configService = $this->createMock(NostoConfigService::class);
        $configService->expects($this->once())
            ->method('get')
            ->with('dailySyncLastTime')
            ->willReturn(null);
        $configService->expects($this->once())
            ->method('set')
            ->with('dailySyncLastTime', $this->isType('string'));

        $nostoSyncRoute = $this->createMock(NostoSyncRoute::class);
        $nostoSyncRoute->expects($this->once())
            ->method('fullCatalogSync')
            ->with(
                $this->isInstanceOf(Request::class),
                $this->callback(static fn (Context $context): bool => $context->getSource() instanceof SystemSource),
            );

        $cache = $this->createMock(TagAwareAdapterInterface::class);
        $cache->expects($this->once())
            ->method('clear')
            ->with(CachedProvider::CACHE_PREFIX);

        $handler = new DailyProductSyncScheduledTaskHandler(
            $scheduledTaskRepository,
            $configProvider,
            $configService,
            $nostoSyncRoute,
            $cache,
            $this->createMock(LoggerInterface::class),
        );

        $handler->run();
    }

    public function testRunSkipsWhenDailyProductSyncIsDisabled(): void
    {
        $scheduledTaskRepository = $this->createMock(EntityRepository::class);
        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('isDailyProductSyncEnabled')->willReturn(false);
        $configProvider->expects($this->never())
            ->method('getDailyProductSyncTime');

        $configService = $this->createMock(NostoConfigService::class);
        $configService->expects($this->never())
            ->method('get');
        $configService->expects($this->never())
            ->method('set');

        $nostoSyncRoute = $this->createMock(NostoSyncRoute::class);
        $nostoSyncRoute->expects($this->never())
            ->method('fullCatalogSync');

        $cache = $this->createMock(TagAwareAdapterInterface::class);
        $cache->expects($this->never())
            ->method('clear');

        $handler = new DailyProductSyncScheduledTaskHandler(
            $scheduledTaskRepository,
            $configProvider,
            $configService,
            $nostoSyncRoute,
            $cache,
            $this->createMock(LoggerInterface::class),
        );

        $handler->run();
    }

    public function testRunSkipsWhenDailyProductSyncTimeIsInvalid(): void
    {
        $scheduledTaskRepository = $this->createMock(EntityRepository::class);
        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('isDailyProductSyncEnabled')->willReturn(true);
        $configProvider->method('getDailyProductSyncTime')->willReturn('not-a-date');

        $configService = $this->createMock(NostoConfigService::class);
        $configService->expects($this->once())
            ->method('get')
            ->with('dailySyncLastTime')
            ->willReturn(null);
        $configService->expects($this->never())
            ->method('set');

        $nostoSyncRoute = $this->createMock(NostoSyncRoute::class);
        $nostoSyncRoute->expects($this->never())
            ->method('fullCatalogSync');

        $cache = $this->createMock(TagAwareAdapterInterface::class);
        $cache->expects($this->never())
            ->method('clear');

        $handler = new DailyProductSyncScheduledTaskHandler(
            $scheduledTaskRepository,
            $configProvider,
            $configService,
            $nostoSyncRoute,
            $cache,
            $this->createMock(LoggerInterface::class),
        );

        $handler->run();
    }
}
