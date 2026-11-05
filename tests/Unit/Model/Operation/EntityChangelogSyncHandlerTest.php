<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Model\Operation;

use Nosto\NostoIntegration\Async\CategorySyncMessage;
use Nosto\NostoIntegration\Async\EntityChangelogSyncMessage;
use Nosto\NostoIntegration\Async\EventsWriter;
use Nosto\NostoIntegration\Async\ProductSyncMessage;
use Nosto\NostoIntegration\Entity\Changelog\ChangelogCollection;
use Nosto\NostoIntegration\Entity\Changelog\ChangelogEntity;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Account;
use Nosto\NostoIntegration\Model\Nosto\Account\Provider as AccountProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\ProductHelper;
use Nosto\NostoIntegration\Model\Operation\EntityChangelogSyncHandler;
use Nosto\Scheduler\Model\Job\JobHelper;
use Nosto\Scheduler\Model\Job\JobResult;
use Nosto\Scheduler\Model\JobScheduler;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AutoIncrementField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\{EntitySearchResult, IdSearchResult};
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\{EqualsAnyFilter, EqualsFilter};
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

    public function testExecuteSchedulesDynamicCategoryProductsAsDirectChangelogChildren(): void
    {
        $context = Context::createDefaultContext();
        $message = new EntityChangelogSyncMessage(Uuid::randomHex(), $context);
        $firstProductStreamId = Uuid::randomHex();
        $secondProductStreamId = Uuid::randomHex();
        $firstProductId = Uuid::randomHex();
        $secondProductId = Uuid::randomHex();

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturnCallback(
            $this->batches([
                EventsWriter::DYNAMIC_CATEGORY_PRODUCT_STREAM_ENTITY_NAME => [[
                    self::event(
                        $firstProductStreamId,
                        EventsWriter::DYNAMIC_CATEGORY_PRODUCT_STREAM_ENTITY_NAME,
                        null,
                    ),
                    self::event(
                        $secondProductStreamId,
                        EventsWriter::DYNAMIC_CATEGORY_PRODUCT_STREAM_ENTITY_NAME,
                        null,
                    ),
                ], [
                    // A repeated stream in a later changelog batch must not schedule its products twice.
                    self::event(
                        $firstProductStreamId,
                        EventsWriter::DYNAMIC_CATEGORY_PRODUCT_STREAM_ENTITY_NAME,
                        null,
                    ),
                ]],
            ]),
        );
        $repository->method('delete')->willReturnCallback(static fn (): EntityWrittenContainerEvent =>
            new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection(), []));

        $productRepository = $this->createMock(EntityRepository::class);
        $productRepository->method('getDefinition')->willReturn($this->createDefinition('product_test'));
        $productSearchCall = 0;
        $productRepository->method('searchIds')->willReturnCallback(
            static function (Criteria $criteria, Context $callContext) use (
                &$productSearchCall,
                $context,
                $firstProductId,
                $firstProductStreamId,
                $secondProductId,
                $secondProductStreamId,
            ): IdSearchResult {
                self::assertSame($context->getVersionId(), $callContext->getVersionId());
                $streamFilter = null;
                foreach ($criteria->getFilters() as $filter) {
                    if ($filter instanceof EqualsAnyFilter && $filter->getField() === 'streams.id') {
                        $streamFilter = $filter;
                    }
                }
                self::assertNotNull($streamFilter);
                self::assertSame([$firstProductStreamId, $secondProductStreamId], $streamFilter->getValue());

                ++$productSearchCall;
                $rows = match ($productSearchCall) {
                    1 => [
                        $firstProductId => [
                            'primaryKey' => $firstProductId,
                            'data' => [
                                'autoIncrement' => 1,
                            ],
                        ],
                    ],
                    2 => [
                        $secondProductId => [
                            'primaryKey' => $secondProductId,
                            'data' => [
                                'autoIncrement' => 2,
                            ],
                        ],
                    ],
                    default => [],
                };

                return new IdSearchResult(count($rows), $rows, $criteria, $callContext);
            },
        );

        $productHelper = $this->createMock(ProductHelper::class);
        $productHelper->expects(self::exactly(2))
            ->method('loadOrderNumberMapping')
            ->willReturnCallback(static fn (array $productIds): array => [
                $productIds[0] => $productIds[0] === $firstProductId ? 'SW-1' : 'SW-2',
            ]);

        $scheduled = [];
        $handler = $this->handler(
            $repository,
            $this->recordingScheduler($scheduled),
            $jobHelperRecorder,
            productRepository: $productRepository,
            productHelper: $productHelper,
        );

        $result = $handler->execute($message);

        self::assertCount(2, $scheduled);
        self::assertInstanceOf(ProductSyncMessage::class, $scheduled[0]);
        self::assertInstanceOf(ProductSyncMessage::class, $scheduled[1]);
        self::assertSame($message->getJobId(), $scheduled[0]->getParentJobId());
        self::assertSame($message->getJobId(), $scheduled[1]->getParentJobId());
        self::assertSame([
            $firstProductId => 'SW-1',
        ], $scheduled[0]->getProductIds());
        self::assertSame([
            $secondProductId => 'SW-2',
        ], $scheduled[1]->getProductIds());
        self::assertSame(
            'Scheduled product synchronization for 2 products from 2 dynamic category streams: 2 child jobs across 1 account.',
            $result->getMessages()[0]->getMessage(),
        );
        self::assertSame(2, $jobHelperRecorder->marks[1][1]);
    }

    public function testExecuteDoesNotQueryDynamicCategoryProductsWithoutAccounts(): void
    {
        $context = Context::createDefaultContext();
        $message = new EntityChangelogSyncMessage(Uuid::randomHex(), $context);
        $productStreamId = Uuid::randomHex();

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturnCallback(
            $this->batches([
                EventsWriter::DYNAMIC_CATEGORY_PRODUCT_STREAM_ENTITY_NAME => [[
                    self::event(
                        $productStreamId,
                        EventsWriter::DYNAMIC_CATEGORY_PRODUCT_STREAM_ENTITY_NAME,
                        null,
                    ),
                ]],
            ]),
        );
        $repository->method('delete')->willReturnCallback(static fn (): EntityWrittenContainerEvent =>
            new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection(), []));

        $productRepository = $this->createMock(EntityRepository::class);
        $productRepository->expects(self::never())->method('getDefinition');
        $productRepository->expects(self::never())->method('searchIds');

        $scheduled = [];
        $handler = $this->handler(
            $repository,
            $this->recordingScheduler($scheduled),
            $jobHelperRecorder,
            productRepository: $productRepository,
            accounts: [],
        );

        $result = $handler->execute($message);

        self::assertSame([], $scheduled);
        self::assertSame(
            'Scheduled product synchronization for 0 products from 1 dynamic category stream: 0 child jobs across 0 accounts.',
            $result->getMessages()[0]->getMessage(),
        );
        self::assertSame(0, $jobHelperRecorder->marks[1][1]);
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
                    return self::searchResult([], $criteria, $context);
                }

                return self::searchResult([self::event($productId, 'product', 'SW-1', $rowId)], $criteria, $context);
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
     * The realistic shape of a stuck row: writes keep arriving, so every batch looks different,
     * and only one row is repeated. Comparing whole batches would never spot it.
     */
    public function testExecuteStopsWhenOneRowSurvivesItsDeletionWhileOtherRowsChange(): void
    {
        $context = Context::createDefaultContext();
        $message = new EntityChangelogSyncMessage(Uuid::randomHex(), $context);
        $stuckRowId = Uuid::randomHex();

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturnCallback(
            static function (Criteria $criteria, Context $context) use ($stuckRowId): EntitySearchResult {
                if (self::extractEntityType($criteria) !== 'product') {
                    return self::searchResult([], $criteria, $context);
                }

                return self::searchResult([
                    self::event(Uuid::randomHex(), 'product', 'SW-STUCK', $stuckRowId),
                    // A different row every time, as new writes land.
                    self::event(Uuid::randomHex(), 'product', 'SW-FRESH'),
                ], $criteria, $context);
            },
        );
        $repository->method('delete')->willReturn(
            new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection(), []),
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $scheduled = [];
        $handler = $this->handler($repository, $this->recordingScheduler($scheduled), $jobHelperRecorder, $logger);

        $handler->execute($message);

        // The first batch is handled, the second shows the repeat and aborts.
        self::assertCount(1, $scheduled);
    }

    /**
     * A run cannot be held open forever by rows arriving faster than they are handled: it stops at
     * the batch limit and leaves the rest to the next scheduled run.
     */
    public function testExecuteStopsAtTheBatchLimitWhenRowsKeepArriving(): void
    {
        $context = Context::createDefaultContext();
        $message = new EntityChangelogSyncMessage(Uuid::randomHex(), $context);

        // Never empties, and never repeats a row id, so only the limit can end the loop.
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturnCallback(
            static function (Criteria $criteria, Context $context): EntitySearchResult {
                if (self::extractEntityType($criteria) !== 'product') {
                    return self::searchResult([], $criteria, $context);
                }

                return self::searchResult([self::event(Uuid::randomHex(), 'product', 'SW-1')], $criteria, $context);
            },
        );
        $repository->method('delete')->willReturn(
            new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection(), []),
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');
        $logger->expects(self::never())->method('error');

        $scheduled = [];
        $handler = $this->handler($repository, $this->recordingScheduler($scheduled), $jobHelperRecorder, $logger);

        $result = $handler->execute($message);

        self::assertInstanceOf(JobResult::class, $result);
        self::assertCount(1000, $scheduled, 'the run is bounded, and everything handled is still scheduled');
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

            return self::searchResult($batchesByType[$entityType][$index] ?? [], $criteria, $context);
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
        ?EntityRepository $productRepository = null,
        ?ProductHelper $productHelper = null,
        ?array $accounts = null,
    ): EntityChangelogSyncHandler {
        $recorder = new EntityChangelogJobHelperRecorder();

        if ($accounts === null) {
            $account = $this->createMock(Account::class);
            $account->method('getChannelId')->willReturn('channel-id');
            $account->method('getLanguageId')->willReturn('language-id');
            $accounts = [$account];
        }

        $accountProvider = $this->createMock(AccountProvider::class);
        $accountProvider->method('all')->willReturn($accounts);

        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('isEnabledMultiCurrency')->willReturn(false);
        $configProvider->method('isEnabledProductSyncExtraLogging')->willReturn(false);

        return new EntityChangelogSyncHandler(
            $repository,
            $productRepository ?? $this->createMock(EntityRepository::class),
            $jobScheduler,
            new EntityChangelogRecordingJobHelper($recorder),
            $configProvider,
            $accountProvider,
            $productHelper ?? $this->createMock(ProductHelper::class),
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
    private static function searchResult(array $events, Criteria $criteria, Context $context): EntitySearchResult
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

    private function createDefinition(string $entityName): EntityDefinition
    {
        $definition = new class($entityName) extends EntityDefinition {
            public function __construct(
                private readonly string $entityName,
            ) {
            }

            public function getEntityName(): string
            {
                return $this->entityName;
            }

            protected function defineFields(): FieldCollection
            {
                return new FieldCollection([new AutoIncrementField()]);
            }
        };

        $definition->compile($this->createMock(DefinitionInstanceRegistry::class));

        return $definition;
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
