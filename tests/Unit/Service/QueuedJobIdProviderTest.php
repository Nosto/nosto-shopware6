<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Service;

use ArrayIterator;
use Doctrine\DBAL\Connection;
use Nosto\NostoIntegration\Service\QueuedJobIdProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shopware\Core\Framework\Uuid\Uuid;

final class QueuedJobIdProviderTest extends TestCase
{
    private const DOCTRINE_DSN = 'doctrine://default?auto_setup=false';

    public function testGetJobIdsCollectsOnlyTheOwnJobIdOfEachQueuedMessage(): void
    {
        $firstJobId = Uuid::randomHex();
        $secondJobId = Uuid::randomHex();
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('iterateColumn')
            ->willReturn(new ArrayIterator([
                sprintf(
                    '{"jobId":"%s","parentJobId":"%s","ids":{"%s":"SKU-1"},"context":{"id":"%s"}}',
                    strtoupper($firstJobId),
                    Uuid::randomHex(),
                    Uuid::randomHex(),
                    Uuid::randomHex(),
                ),
                sprintf('{"parentJobId":"%s", "jobId" : "%s"}', Uuid::randomHex(), $secondJobId),
                sprintf('{"jobId":"%s"}', $firstJobId),
            ]));

        $jobIds = (new QueuedJobIdProvider($connection, self::DOCTRINE_DSN))->getJobIds();

        self::assertSame([$firstJobId, $secondJobId], array_keys($jobIds));
    }

    public function testGetJobIdsFailsInsteadOfGuessingWhenAQueuedMessageCannotBeRead(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('iterateColumn')->willReturn(new ArrayIterator(['not a readable message']));

        $this->expectException(RuntimeException::class);

        (new QueuedJobIdProvider($connection, self::DOCTRINE_DSN))->getJobIds();
    }

    public function testGetJobIdsAlsoLooksAtTheMessageHeaders(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('iterateColumn')
            ->with($this->callback(
                static fn (string $query): bool => str_contains($query, '`body` LIKE')
                    && str_contains($query, '`headers` LIKE'),
            ))
            ->willReturn(new ArrayIterator([]));

        (new QueuedJobIdProvider($connection, self::DOCTRINE_DSN))->getJobIds();
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
