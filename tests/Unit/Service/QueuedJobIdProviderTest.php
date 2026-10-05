<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Service;

use ArrayIterator;
use Doctrine\DBAL\Connection;
use Nosto\NostoIntegration\Service\QueuedJobIdProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;

final class QueuedJobIdProviderTest extends TestCase
{
    private const DOCTRINE_DSN = 'doctrine://default?auto_setup=false';

    public function testGetJobIdsCollectsTheIdsOfAllQueuedNostoMessages(): void
    {
        $jobId = Uuid::randomHex();
        $parentJobId = Uuid::randomHex();
        $serializedJobId = Uuid::randomHex();
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('iterateColumn')
            ->willReturn(new ArrayIterator([
                sprintf('{"jobId":"%s","parentJobId":"%s"}', $jobId, $parentJobId),
                sprintf('O:8:"Message":1:{s:5:"jobId";s:32:"%s";}', strtoupper($serializedJobId)),
                sprintf('{"jobId":"%s"}', $jobId),
            ]));

        $jobIds = (new QueuedJobIdProvider($connection, self::DOCTRINE_DSN))->getJobIds();

        self::assertSame([$jobId, $parentJobId, $serializedJobId], array_keys($jobIds));
    }

    public function testGetJobIdsReturnsNothingForAnEmptyQueue(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('iterateColumn')->willReturn(new ArrayIterator([]));

        self::assertSame([], (new QueuedJobIdProvider($connection, self::DOCTRINE_DSN))->getJobIds());
    }

    public function testIsAvailableOnlyForTheDoctrineTransport(): void
    {
        $connection = $this->createMock(Connection::class);

        self::assertTrue((new QueuedJobIdProvider($connection, self::DOCTRINE_DSN))->isAvailable());
        self::assertFalse((new QueuedJobIdProvider($connection, 'redis://localhost:6379/messages'))->isAvailable());
        self::assertFalse(
            (new QueuedJobIdProvider($connection, 'amqp://guest:guest@localhost:5672/%2f/messages'))->isAvailable(),
        );
    }
}
