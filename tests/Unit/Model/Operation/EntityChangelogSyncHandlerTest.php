<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Model\Operation;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result as DbalResult;
use Nosto\NostoIntegration\Async\CategorySyncMessage;
use Nosto\NostoIntegration\Async\EntityChangelogSyncMessage;
use Nosto\NostoIntegration\Async\ProductSyncMessage;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Account;
use Nosto\NostoIntegration\Model\Nosto\Account\Provider as AccountProvider;
use Nosto\NostoIntegration\Model\Operation\EntityChangelogSyncHandler;
use Nosto\Scheduler\Model\Job\JobHelper;
use Nosto\Scheduler\Model\Job\JobResult;
use Nosto\Scheduler\Model\JobScheduler;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

final class EntityChangelogSyncHandlerTest extends TestCase
{
    public function testExecuteSchedulesJobsPerEntityAndDeletesUpToTheWatermark(): void
    {
        $context = Context::createDefaultContext();
        $message = new EntityChangelogSyncMessage(Uuid::randomHex(), $context);

        $productId = Uuid::randomHex();
        $categoryId = Uuid::randomHex();
        $watermark = Uuid::randomHex();

        $batchCalls = [];
        $connection = $this->createMock(Connection::class);

        // Only product and category have anything pending; every other type is skipped.
        $connection->method('fetchOne')->willReturnCallback(
            static fn (string $sql, array $params = []): string|false => in_array(
                $params['entityType'] ?? null,
                ['product', 'category'],
                true,
            ) ? $watermark : false,
        );

        $connection->method('executeQuery')->willReturnCallback(
            function (string $sql, array $params = [], array $types = []) use (
                &$batchCalls,
                $productId,
                $categoryId
            ): DbalResult {
                $entityType = $params['entityType'] ?? '';

                // The latest-product-number lookup, not a batch read.
                if (str_contains($sql, 'INNER JOIN')) {
                    return $this->dbalResult([[
                        'entity_id' => $productId,
                        'productNumber' => 'SW-DEMO-NEWEST',
                    ]]);
                }

                $batchCalls[$entityType] = ($batchCalls[$entityType] ?? 0) + 1;
                if ($batchCalls[$entityType] > 1) {
                    return $this->dbalResult([]);
                }

                if ($entityType === 'product') {
                    // Three rows for one product: they collapse into a single scheduled entity.
                    return $this->dbalResult([
                        [
                            'entity_id' => $productId,
                        ],
                        [
                            'entity_id' => $productId,
                        ],
                        [
                            'entity_id' => $productId,
                        ],
                    ]);
                }

                if ($entityType === 'category') {
                    return $this->dbalResult([[
                        'entity_id' => $categoryId,
                    ]]);
                }

                return $this->dbalResult([]);
            },
        );

        $deletes = [];
        $connection->method('executeStatement')->willReturnCallback(
            static function (string $sql, array $params = [], array $types = []) use (&$deletes): int {
                $deletes[] = [
                    'sql' => $sql,
                    'params' => $params,
                ];

                return 1;
            },
        );

        $scheduledMessages = [];
        $jobScheduler = $this->createMock(JobScheduler::class);
        $jobScheduler->method('schedule')->willReturnCallback(
            static function (object $job) use (&$scheduledMessages): void {
                $scheduledMessages[] = $job;
            },
        );

        $jobHelperRecorder = new EntityChangelogJobHelperRecorder();
        $handler = new EntityChangelogSyncHandler(
            $connection,
            $jobScheduler,
            new EntityChangelogRecordingJobHelper($jobHelperRecorder),
            $this->configProvider(),
            $this->accountProvider(),
            $this->createMock(LoggerInterface::class),
        );

        $result = $handler->execute($message);

        self::assertInstanceOf(JobResult::class, $result);
        self::assertCount(2, $scheduledMessages);
        self::assertInstanceOf(ProductSyncMessage::class, $scheduledMessages[0]);
        self::assertInstanceOf(CategorySyncMessage::class, $scheduledMessages[1]);

        // Three rows, one scheduled product, and the number comes from the latest pending row
        // rather than from the newest row inside the batch.
        self::assertSame([
            $productId => 'SW-DEMO-NEWEST',
        ], $scheduledMessages[0]->getProductIds());
        self::assertSame([$categoryId], array_values($scheduledMessages[1]->getCategoryIds()));

        // Every delete is bounded by the watermark, so rows written mid-run are never removed.
        self::assertNotEmpty($deletes);
        foreach ($deletes as $delete) {
            self::assertStringContainsString('`id` <= :watermark', $delete['sql']);
            self::assertSame(Uuid::fromHexToBytes($watermark), $delete['params']['watermark']);
        }
        self::assertSame(
            [Uuid::fromHexToBytes($productId)],
            $deletes[0]['params']['entityIds'],
        );
    }

    public function testExecuteSkipsEntityTypesWithNothingPending(): void
    {
        $context = Context::createDefaultContext();
        $message = new EntityChangelogSyncMessage(Uuid::randomHex(), $context);

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(false);
        // Nothing pending means no batch is ever read and nothing is deleted.
        $connection->expects(self::never())->method('executeQuery');
        $connection->expects(self::never())->method('executeStatement');

        $handler = new EntityChangelogSyncHandler(
            $connection,
            $this->createMock(JobScheduler::class),
            new EntityChangelogRecordingJobHelper(new EntityChangelogJobHelperRecorder()),
            $this->configProvider(),
            $this->accountProvider(),
            $this->createMock(LoggerInterface::class),
        );

        self::assertInstanceOf(JobResult::class, $handler->execute($message));
    }

    public function testExecuteStopsWhenABatchSurvivesItsDeletion(): void
    {
        $context = Context::createDefaultContext();
        $message = new EntityChangelogSyncMessage(Uuid::randomHex(), $context);
        $productId = Uuid::randomHex();
        $watermark = Uuid::randomHex();

        // The same entity comes back every time: the delete removes nothing.
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturnCallback(
            static fn (string $sql, array $params = []): string|false
                => ($params['entityType'] ?? null) === 'product' ? $watermark : false,
        );
        $connection->method('executeQuery')->willReturnCallback(
            function (string $sql, array $params = [], array $types = []) use ($productId): DbalResult {
                if (str_contains($sql, 'INNER JOIN')) {
                    return $this->dbalResult([[
                        'entity_id' => $productId,
                        'productNumber' => 'SW-1',
                    ]]);
                }

                return $this->dbalResult([[
                    'entity_id' => $productId,
                ]]);
            },
        );
        $connection->method('executeStatement')->willReturn(0);

        $scheduledMessages = [];
        $jobScheduler = $this->createMock(JobScheduler::class);
        $jobScheduler->method('schedule')->willReturnCallback(
            static function (object $job) use (&$scheduledMessages): void {
                $scheduledMessages[] = $job;
            },
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $handler = new EntityChangelogSyncHandler(
            $connection,
            $jobScheduler,
            new EntityChangelogRecordingJobHelper(new EntityChangelogJobHelperRecorder()),
            $this->configProvider(),
            $this->accountProvider(),
            $logger,
        );

        $result = $handler->execute($message);

        // Handled once, then the guard aborts instead of looping forever.
        self::assertInstanceOf(JobResult::class, $result);
        self::assertCount(1, $scheduledMessages);
        self::assertInstanceOf(ProductSyncMessage::class, $scheduledMessages[0]);
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function dbalResult(array $rows): DbalResult
    {
        $result = $this->createMock(DbalResult::class);
        $result->method('fetchAllAssociative')->willReturn($rows);

        return $result;
    }

    private function configProvider(): ConfigProvider
    {
        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('isEnabledMultiCurrency')->willReturn(false);
        $configProvider->method('isEnabledProductSyncExtraLogging')->willReturn(false);

        return $configProvider;
    }

    private function accountProvider(): AccountProvider
    {
        $account = $this->createMock(Account::class);
        $account->method('getChannelId')->willReturn('channel-id');
        $account->method('getLanguageId')->willReturn('language-id');

        $accountProvider = $this->createMock(AccountProvider::class);
        $accountProvider->method('all')->willReturn([$account]);

        return $accountProvider;
    }
}

final class EntityChangelogJobHelperRecorder
{
    /**
     * @var array<int, array{0: string, 1: int, 2: bool}>
     */
    public array $marks = [];
}

readonly class EntityChangelogRecordingJobHelper extends JobHelper
{
    public function __construct(
        private EntityChangelogJobHelperRecorder $recorder,
    ) {
    }

    public function markChildGenerationState(string $jobId, int $count, bool $done): void
    {
        $this->recorder->marks[] = [$jobId, $count, $done];
    }
}
