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

    public function testGetJobIdsCollectsOnlyTheOwnJobIdOfEachQueuedJobMessage(): void
    {
        $firstJobId = Uuid::randomHex();
        $secondJobId = Uuid::randomHex();
        $connection = $this->createConnection([
            $this->createRow(
                'Nosto\\NostoIntegration\\Async\\ProductSyncMessage',
                sprintf(
                    '{"jobId":"%s","parentJobId":"%s","ids":{"%s":"SKU-1"},"context":{"id":"%s"}}',
                    strtoupper($firstJobId),
                    Uuid::randomHex(),
                    Uuid::randomHex(),
                    Uuid::randomHex(),
                ),
            ),
            $this->createRow(
                'Nosto\\NostoIntegration\\Async\\CategorySyncMessage',
                sprintf('{"parentJobId":"%s", "jobId" : "%s"}', Uuid::randomHex(), $secondJobId),
            ),
            $this->createRow(
                'Nosto\\NostoIntegration\\Async\\ProductSyncMessage',
                sprintf('{"jobId":"%s"}', $firstJobId),
            ),
        ]);

        $jobIds = (new QueuedJobIdProvider($connection, self::DOCTRINE_DSN))->getJobIds();

        self::assertSame([$firstJobId, $secondJobId], array_keys($jobIds));
    }

    public function testGetJobIdsIgnoresMessagesThatAreNotNostoJobMessages(): void
    {
        $jobId = Uuid::randomHex();
        $connection = $this->createConnection([
            $this->createRow(
                'Nosto\\NostoIntegration\\Service\\ScheduledTask\\OrphanedJobRecoveryScheduledTask',
                '{"taskId":"' . Uuid::randomHex() . '"}',
            ),
            $this->createRow('Shopware\\Core\\Framework\\MessageQueue\\Foo', '{"jobId":"' . Uuid::randomHex() . '"}'),
            [
                'headers' => 'not json',
                'body' => '{}',
            ],
            $this->createRow('Nosto\\NostoIntegration\\Async\\ProductSyncMessage', '{"jobId":"' . $jobId . '"}'),
        ]);

        $jobIds = (new QueuedJobIdProvider($connection, self::DOCTRINE_DSN))->getJobIds();

        self::assertSame([$jobId], array_keys($jobIds));
    }

    public function testGetJobIdsFailsInsteadOfGuessingWhenAJobMessageCannotBeRead(): void
    {
        $connection = $this->createConnection([
            $this->createRow('Nosto\\NostoIntegration\\Async\\ProductSyncMessage', 'not a readable message'),
        ]);

        $this->expectException(RuntimeException::class);

        (new QueuedJobIdProvider($connection, self::DOCTRINE_DSN))->getJobIds();
    }

    public function testGetJobIdsSkipsTheFailedQueue(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('iterateAssociative')
            ->with(
                $this->callback(
                    static fn (string $query): bool => str_contains($query, '`queue_name` <> :failedQueue'),
                ),
                $this->callback(static fn (array $params): bool => $params['failedQueue'] === 'failed'),
            )
            ->willReturn(new ArrayIterator([]));

        (new QueuedJobIdProvider($connection, self::DOCTRINE_DSN))->getJobIds();
    }

    public function testGetJobIdsReturnsNothingForAnEmptyQueue(): void
    {
        self::assertSame([], (new QueuedJobIdProvider($this->createConnection([]), self::DOCTRINE_DSN))->getJobIds());
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

    /**
     * @param list<array{headers: string, body: string}> $rows
     */
    private function createConnection(array $rows): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('iterateAssociative')->willReturn(new ArrayIterator($rows));

        return $connection;
    }

    /**
     * @return array{headers: string, body: string}
     */
    private function createRow(string $messageClass, string $body): array
    {
        return [
            'headers' => (string) json_encode([
                'type' => $messageClass,
                'Content-Type' => 'application/json',
            ]),
            'body' => $body,
        ];
    }
}
