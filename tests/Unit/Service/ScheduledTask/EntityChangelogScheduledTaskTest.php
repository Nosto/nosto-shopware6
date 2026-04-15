<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Service\ScheduledTask;

use Nosto\NostoIntegration\Service\ScheduledTask\EntityChangelogScheduledTask;
use PHPUnit\Framework\TestCase;

final class EntityChangelogScheduledTaskTest extends TestCase
{
    public function testTaskMetadataMatchesTheChangelogSchedule(): void
    {
        self::assertSame('nosto_integration_entity_changelog_task', EntityChangelogScheduledTask::getTaskName());
        self::assertSame(600, EntityChangelogScheduledTask::getDefaultInterval());
    }
}
