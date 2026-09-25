<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Model\Operation;

use Nosto\NostoIntegration\Async\CategorySyncMessage;
use Nosto\NostoIntegration\Async\EntityChangelogSyncMessage;
use Nosto\NostoIntegration\Async\ProductSyncMessage;
use Nosto\NostoIntegration\Entity\Changelog\ChangelogCollection;
use Nosto\NostoIntegration\Entity\Changelog\ChangelogEntity;
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
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Uuid\Uuid;

final class EntityChangelogSyncHandlerTest extends TestCase
{
    public function testExecuteSchedulesProductAndCategoryJobsAndDeletesProcessedRows(): void
    {
        $context = Context::createDefaultContext();
        $message = new EntityChangelogSyncMessage(Uuid::randomHex(), $context);

        $productId = Uuid::randomHex();
        $categoryId = Uuid::randomHex();

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturnCallback(
            $this->batches([
                // Three rows for one product: they collapse into a single payload entry, and the
                // newest product number wins.
                'product' => [[
                    self::event($productId, 'product', 'SW-DEMO-OLD'),
                    self::event($productId, 'product', 'SW-DEMO-MID'),
                    self::event($productId, 'product', 'SW-DEMO-1'),
                ]],
                'category' => [[self::event($categoryId, 'category', null)]],
            ]),
        );

        $deletedRows = [];
        $repository->method('delete')->willReturnCallback($this->recordDeletes($deletedRows));

        $scheduled = [];
        $handler = $this->handler($repository, $this->recordingScheduler($scheduled), $jobHelperRecorder);

        $result = $handler->execute($message);

        self::assertInstanceOf(JobResult::class, $result);
        self::assertCount(2, $scheduled);
        self::assertInstanceOf(ProductSyncMessage::class, $scheduled[0]);
        self::assertInstanceOf(CategorySyncMessage::class, $scheduled[1]);
        self::assertSame([
            $productId => 'SW-DEMO-1',
        ], $scheduled[0]->getProductIds());
        self::assertSame([$categoryId], array_values($scheduled[1]->getCategoryIds()));

        // Exactly the rows that were read are removed, by row id, never by entity id.
        self::assertCount(3, $deletedRows[0]);
        self::assertSame(['id'], array_keys($deletedRows[0][0]));
        self::assertCount(1, $deletedRows[1]);
        self::assertSame(2, $jobHelperRecorder->marks[1][1]);
    }

    /**
     * Guards the bug this handler was changed for: the previous implementation advanced an OFFSET
     * past rows it had just deleted, so every second batch was skipped. Three batches in, three
     * batches scheduled, every row removed.
     */
    public function testExecuteSchedulesEveryBatchWhenMoreRowsArePendingThanOneBatchHolds(): void
    {
        $context = Context::createDefaultContext();
        $message = new EntityChangelogSyncMessage(Uuid::randomHex(), $context);

        $first = [Uuid::randomHex(), Uuid::randomHex()];
        $second = [Uuid::randomHex(), Uuid::randomHex()];
        $third = [Uuid::randomHex()];

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturnCallback(
            $this->batches([
                'product' => [
                    array_map(static fn (string $id) => self::event($id, 'product', 'SW-' . $id), $first),
                    array_map(static fn (string $id) => self::event($id, 'product', 'SW-' . $id), $second),
                    array_map(static fn (string $id) => self::event($id, 'product', 'SW-' . $id), $third),
                ],
            ]),
        );

        $deletedRows = [];
        $repository->method('delete')->willReturnCallback($this->recordDeletes($deletedRows));

        $scheduled = [];
        $handler = $this->handler($repository, $this->recordingScheduler($scheduled), $jobHelperRecorder);

        $handler->execute($message);

        // One product job per batch, none skipped.
        self::assertCount(3, $scheduled);
        $scheduledIds = array_merge(
            array_keys($scheduled[0]->getProductIds()),
            array_keys($scheduled[1]->getProductIds()),
            array_keys($scheduled[2]->getProductIds()),
        );
        self::assertSame(
            array_merge($first, $second, $third),
            $scheduledIds,
            'every pending entity must be scheduled exactly once, in order',
        );

        // And every batch's rows are removed, so nothing is left behind for a later run.
        self::assertCount(3, $deletedRows);
        self::assertSame([2, 2, 1], array_map('count', $deletedRows));
    }

    public function testExecuteStopsWhenABatchSurvivesItsDeletion(): void
    {
        $context = Context::createDefaultContext();
        $message = new EntityChangelogSyncMessage(Uuid::randomHex(), $context);
        $productId = Uuid::randomHex();
        $rowId = Uuid::randomHex();

        // The same row, with the same row id, comes back every time: the delete removes nothing.
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturnCallback(
            static function (Criteria $criteria, Context $context) use ($productId, $rowId): EntitySearchResult {
                if (self::extractEntityType($criteria) !== 'product') {
                    return self::result([], $criteria, $context);
                }

                return self::result([self::event($productId, 'product', 'SW-1', $rowId)], $criteria, $context);
            },
        );
        $repository->method('delete')->willReturn(
            new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection(), []),
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $scheduled = [];
        $handler = $this->handler(
            $repository,
            $this->recordingScheduler($scheduled),
            $jobHelperRecorder,
            $logger,
        );

        $result = $handler->execute($message);

        // Handled once, then the guard aborts instead of looping forever.
        self::assertInstanceOf(JobResult::class, $result);
        self::assertCount(1, $scheduled);
        self::assertInstanceOf(ProductSyncMessage::class, $scheduled[0]);
    }

    /**
     * Serves the given batches per entity type, in order, then empties.
     *
     * @param array<string, list<list<ChangelogEntity>>> $batchesByType
     */
    private function batches(array $batchesByType): callable
    {
        $calls = [];

        return static function (Criteria $criteria, Context $context) use (
            $batchesByType,
            &$calls
        ): EntitySearchResult {
            $entityType = self::extractEntityType($criteria) ?? '';
            $index = $calls[$entityType] ?? 0;
            $calls[$entityType] = $index + 1;

            return self::result($batchesByType[$entityType][$index] ?? [], $criteria, $context);
        };
    }

    /**
     * @param list<list<array<string, string>>> $deletedRows
     */
    private function recordDeletes(array &$deletedRows): callable
    {
        return static function (array $rows) use (&$deletedRows): EntityWrittenContainerEvent {
            $deletedRows[] = $rows;

            return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection(), []);
        };
    }

    /**
     * @param list<object> $scheduled
     */
    private function recordingScheduler(array &$scheduled): JobScheduler
    {
        $jobScheduler = $this->createMock(JobScheduler::class);
        $jobScheduler->method('schedule')->willReturnCallback(
            static function (object $job) use (&$scheduled): void {
                $scheduled[] = $job;
            },
        );

        return $jobScheduler;
    }

    private function handler(
        EntityRepository $repository,
        JobScheduler $jobScheduler,
        ?EntityChangelogJobHelperRecorder &$recorder = null,
        ?LoggerInterface $logger = null,
    ): EntityChangelogSyncHandler {
        $recorder = new EntityChangelogJobHelperRecorder();

        $account = $this->createMock(Account::class);
        $account->method('getChannelId')->willReturn('channel-id');
        $account->method('getLanguageId')->willReturn('language-id');

        $accountProvider = $this->createMock(AccountProvider::class);
        $accountProvider->method('all')->willReturn([$account]);

        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('isEnabledMultiCurrency')->willReturn(false);
        $configProvider->method('isEnabledProductSyncExtraLogging')->willReturn(false);

        return new EntityChangelogSyncHandler(
            $repository,
            $jobScheduler,
            new EntityChangelogRecordingJobHelper($recorder),
            $configProvider,
            $accountProvider,
            $logger ?? $this->createMock(LoggerInterface::class),
        );
    }

    private static function extractEntityType(Criteria $criteria): ?string
    {
        foreach ($criteria->getFilters() as $filter) {
            if ($filter instanceof EqualsFilter && $filter->getField() === 'entityType') {
                return (string) $filter->getValue();
            }
        }

        return null;
    }

    private static function event(
        string $entityId,
        string $entityType,
        ?string $productNumber,
        ?string $rowId = null,
    ): ChangelogEntity {
        $event = new ChangelogEntity();
        $event->setId($rowId ?? Uuid::randomHex());
        $event->setEntityType($entityType);
        $event->setEntityId($entityId);
        $event->setProductNumber($productNumber);

        return $event;
    }

    /**
     * @param list<ChangelogEntity> $events
     */
    private static function result(array $events, Criteria $criteria, Context $context): EntitySearchResult
    {
        return new EntitySearchResult(
            ChangelogEntity::class,
            count($events),
            new ChangelogCollection($events),
            new AggregationResultCollection(),
            $criteria,
            $context,
        );
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
