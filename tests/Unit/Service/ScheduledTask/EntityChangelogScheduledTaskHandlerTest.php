<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Service\ScheduledTask;

use Nosto\NostoIntegration\Async\EntityChangelogSyncMessage;
use Nosto\NostoIntegration\Service\ScheduledTask\EntityChangelogScheduledTaskHandler;
use Nosto\Scheduler\Model\JobScheduler;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

final class EntityChangelogScheduledTaskHandlerTest extends TestCase
{
    public function testRunSchedulesTheEntityChangelogSyncJob(): void
    {
        $scheduledMessages = [];
        $jobScheduler = $this->createMock(JobScheduler::class);
        $jobScheduler->expects($this->once())
            ->method('schedule')
            ->willReturnCallback(static function (object $message) use (&$scheduledMessages): void {
                $scheduledMessages[] = $message;
            });

        $handler = new EntityChangelogScheduledTaskHandler(
            $this->createMock(EntityRepository::class),
            $jobScheduler,
            $this->createMock(LoggerInterface::class),
        );

        $handler->run();

        self::assertCount(1, $scheduledMessages);
        self::assertInstanceOf(EntityChangelogSyncMessage::class, $scheduledMessages[0]);
    }
}
