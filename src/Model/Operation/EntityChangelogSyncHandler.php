<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Operation;

use Nosto\NostoIntegration\Async\CategorySyncMessage;
use Nosto\NostoIntegration\Async\EntityChangelogSyncMessage;
use Nosto\NostoIntegration\Async\EventsWriter;
use Nosto\NostoIntegration\Async\ExchangeRateSyncMessage;
use Nosto\NostoIntegration\Async\MarketingPermissionSyncMessage;
use Nosto\NostoIntegration\Async\OrderSyncMessage;
use Nosto\NostoIntegration\Async\ProductSyncMessage;
use Nosto\NostoIntegration\Entity\Changelog\ChangelogEntity;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Account\Provider as AccountProvider;
use Nosto\NostoIntegration\Utils\NostoCriteriaFactory;
use Nosto\Scheduler\Model\Job\{
    GeneratingHandlerInterface,
    JobHandlerInterface,
    JobHelper,
    JobResult,
    Message\InfoMessage
};
use Nosto\Scheduler\Model\JobScheduler;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;

class EntityChangelogSyncHandler implements JobHandlerInterface, GeneratingHandlerInterface
{
    public const HANDLER_CODE = 'nosto-integration-entity-changelog-sync';

    private const BATCH_SIZE = 100;

    public function __construct(
        private readonly EntityRepository $entityChangelogRepository,
        private readonly JobScheduler $jobScheduler,
        private readonly JobHelper $jobHelper,
        private readonly ConfigProvider $configProvider,
        private readonly AccountProvider $accountProvider,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param EntityChangelogSyncMessage $message
     */
    public function execute(object $message): JobResult
    {
        $result = new JobResult();
        $shouldLogExtra = $this->shouldLogExtra();
        $syncStartedAt = $shouldLogExtra ? microtime(true) : null;
        $scheduledChildCount = 0;

        $this->jobHelper->markChildGenerationState($message->getJobId(), 0, false);

        $scheduledChildCount += $this->processMarketingPermissionEvents(
            $message->getContext(),
            $result,
            $message->getJobId(),
        );
        $scheduledChildCount += $this->processNewOrderEvents($message->getContext(), $result, $message->getJobId());
        $scheduledChildCount += $this->processUpdatedOrderEvents($message->getContext(), $result, $message->getJobId());
        $scheduledChildCount += $this->processProductEvents($message->getContext(), $result, $message->getJobId());
        $scheduledChildCount += $this->processCategoryEvents($message->getContext(), $result, $message->getJobId());
        if ($this->configProvider->isEnabledMultiCurrency()) {
            $scheduledChildCount += $this->processExchangeRateEvents(
                $message->getContext(),
                $result,
                $message->getJobId(),
            );
        }

        $this->jobHelper->markChildGenerationState($message->getJobId(), $scheduledChildCount, true);

        if ($shouldLogExtra && $syncStartedAt !== null) {
            $this->logDuration(
                $message->getContext(),
                'product_sync.changelog.execute',
                $syncStartedAt,
            );
        }

        return $result;
    }

    private function processMarketingPermissionEvents(Context $context, JobResult $result, string $parentJobId): int
    {
        $type = EventsWriter::NEWSLETTER_ENTITY_NAME;
        return $this->processEventBatches($context, $type, 'product_sync.changelog.newsletter', function (
            array $subscriberIds,
        ) use (
            $parentJobId,
            $result,
            $context
        ): int {
            $jobMessage = new MarketingPermissionSyncMessage(Uuid::randomHex(), $parentJobId, $subscriberIds, $context);
            $this->jobScheduler->schedule($jobMessage);
            $result->addMessage(new InfoMessage(
                sprintf(
                    'Job with payload of %s marketing permission updates has been scheduled.',
                    count($subscriberIds),
                ),
            ));

            return 1;
        });
    }

    private function processEventBatches(
        Context $context,
        string $entityType,
        string $metricPrefix,
        callable $processCallback,
    ): int {
        $shouldLogExtra = $this->shouldLogExtra();
        $batchIndex = 0;
        $eventCount = 0;
        $payloadCount = 0;
        $scheduledChildCount = 0;
        $iteratorStartedAt = $shouldLogExtra ? microtime(true) : null;
        $previousRowIds = null;

        // Each batch takes the oldest pending rows and removes exactly those rows once handled, so
        // the next batch is always at the start of the result set. Paginating with an offset here
        // would skip one batch for every batch handled. Rows are collapsed per entity for the
        // payload, so an entity written several times within a batch is scheduled once.
        while (($events = $this->fetchOldestEventBatch($entityType, $context))->count() > 0) {
            $rowIds = array_values($events->getIds());
            $sortedRowIds = $rowIds;
            sort($sortedRowIds);

            // Safety net: if a batch survives its delete, stop instead of looping over it forever.
            if ($previousRowIds === $sortedRowIds) {
                $this->logger->error(
                    'Nosto: changelog batch was still present after deletion, aborting to avoid an endless loop.',
                    [
                        'entity_type' => $entityType,
                        'batch_size' => count($rowIds),
                    ],
                );

                break;
            }

            $previousRowIds = $sortedRowIds;

            ++$batchIndex;
            $batchStartedAt = $shouldLogExtra ? microtime(true) : null;
            $ids = $this->getPayloadFromBatch($entityType, $this->collapsePerEntity($events));

            $batchEventCount = count($rowIds);
            $batchPayloadCount = count($ids);
            $eventCount += $batchEventCount;
            $payloadCount += $batchPayloadCount;

            $scheduledChildCount += $processCallback($ids);
            $deleteStartedAt = $shouldLogExtra ? microtime(true) : null;
            $this->deleteEvents($rowIds, $context);

            if ($shouldLogExtra && $deleteStartedAt !== null) {
                $this->logDuration(
                    $context,
                    $metricPrefix . '.delete',
                    $deleteStartedAt,
                    [
                        'entity_type' => $entityType,
                        'event_count' => $batchEventCount,
                    ],
                );
            }
            if ($shouldLogExtra && $batchStartedAt !== null) {
                $this->logDuration(
                    $context,
                    $metricPrefix . '.batch',
                    $batchStartedAt,
                    [
                        'entity_type' => $entityType,
                        'batch_index' => $batchIndex,
                        'event_count' => $batchEventCount,
                        'payload_count' => $batchPayloadCount,
                    ],
                );
            }
        }

        if ($shouldLogExtra && $iteratorStartedAt !== null && $batchIndex > 0) {
            $this->logDuration(
                $context,
                $metricPrefix . '.total',
                $iteratorStartedAt,
                [
                    'entity_type' => $entityType,
                    'batch_count' => $batchIndex,
                    'event_count' => $eventCount,
                    'payload_count' => $payloadCount,
                ],
            );
        }

        return $scheduledChildCount;
    }

    /**
     * Reads the oldest pending rows of one entity type.
     *
     * Ordering is by primary key rather than by createdAt. Shopware generates UUIDv7, whose leading
     * bits are a millisecond timestamp, so the key is already in creation order - at a finer
     * resolution than createdAt, and without the ties a datetime column allows. InnoDB appends the
     * primary key to every secondary index, so the existing entity_type index serves this ordering
     * without a filesort.
     */
    private function fetchOldestEventBatch(string $entityType, Context $context): EntityCollection
    {
        $criteria = NostoCriteriaFactory::create('product_sync.changelog.batch');
        $criteria->addFilter(new EqualsFilter('entityType', $entityType));
        $criteria->addSorting(new FieldSorting('id', FieldSorting::ASCENDING));
        $criteria->setLimit(self::BATCH_SIZE);

        return $this->entityChangelogRepository->search($criteria, $context)->getEntities();
    }

    /**
     * Collapses a batch so an entity written several times within it is scheduled once.
     *
     * @return array<string, string|null> entity id => product number
     */
    private function collapsePerEntity(EntityCollection $events): array
    {
        $batch = [];
        /** @var ChangelogEntity $event */
        foreach ($events as $event) {
            // Rows arrive oldest first, so the last value seen is the most recent one in this batch.
            $batch[$event->getEntityId()] = $event->getProductNumber();
        }

        return $batch;
    }

    /**
     * @param array<string, string|null> $batch
     * @return array<string, string|null>|list<string>
     */
    private function getPayloadFromBatch(string $entityType, array $batch): array
    {
        if ($entityType !== ProductDefinition::ENTITY_NAME
            && $entityType !== EventsWriter::ORDER_ENTITY_PLACED_NAME
        ) {
            return array_keys($batch);
        }

        return $batch;
    }

    /**
     * Removes exactly the rows that were read.
     *
     * Deleting by entity id instead would also remove rows written after the read - losing changes
     * no later run would pick up - and would discard newer product numbers held by rows this batch
     * did not see. Bounding that by a MAX(id) watermark is not safe either: UUIDv7 carries the
     * writing host's clock, so a row written mid-run on a host running behind can sort below the
     * watermark and be deleted unprocessed.
     *
     * @param list<string> $rowIds
     */
    private function deleteEvents(array $rowIds, Context $context): void
    {
        if ($rowIds === []) {
            return;
        }

        $this->entityChangelogRepository->delete(
            array_map(static fn (string $id): array => [
                'id' => $id,
            ], $rowIds),
            $context,
        );
    }

    private function processNewOrderEvents(Context $context, JobResult $result, string $parentJobId): int
    {
        $type = EventsWriter::ORDER_ENTITY_PLACED_NAME;
        return $this->processEventBatches($context, $type, 'product_sync.changelog.order_placed', function (
            array $orderIds,
        ) use (
            $parentJobId,
            $result,
            $context
        ): int {
            $jobMessage = new OrderSyncMessage(
                Uuid::randomHex(),
                $parentJobId,
                $orderIds,
                [],
                $context,
                'New Order Sync Operation',
            );
            $this->jobScheduler->schedule($jobMessage);
            $result->addMessage(new InfoMessage(
                sprintf('Job with payload of %s new orders has been scheduled.', count($orderIds)),
            ));

            return 1;
        });
    }

    private function processUpdatedOrderEvents(Context $context, JobResult $result, string $parentJobId): int
    {
        $type = EventsWriter::ORDER_ENTITY_UPDATED_NAME;
        return $this->processEventBatches($context, $type, 'product_sync.changelog.order_updated', function (
            array $orderIds,
        ) use (
            $parentJobId,
            $result,
            $context
        ): int {
            $jobMessage = new OrderSyncMessage(
                Uuid::randomHex(),
                $parentJobId,
                [],
                $orderIds,
                $context,
                'Updated Order Sync Operation',
            );
            $this->jobScheduler->schedule($jobMessage);
            $result->addMessage(new InfoMessage(
                sprintf('Job with payload of %s updated orders has been scheduled.', count($orderIds)),
            ));

            return 1;
        });
    }

    private function processProductEvents(Context $context, JobResult $result, string $parentJobId): int
    {
        $type = EventsWriter::PRODUCT_ENTITY_NAME;
        return $this->processEventBatches($context, $type, 'product_sync.changelog.product', function (
            array $productIds,
        ) use (
            $parentJobId,
            $result,
            $context
        ): int {
            $accountCount = 0;
            foreach ($this->accountProvider->all($context) as $account) {
                $jobMessage = new ProductSyncMessage(
                    Uuid::randomHex(),
                    $parentJobId,
                    $productIds,
                    $context,
                    null,
                    $account->getChannelId(),
                    $account->getLanguageId(),
                );
                $this->jobScheduler->schedule($jobMessage);
                ++$accountCount;
            }

            $result->addMessage(new InfoMessage(
                sprintf(
                    'Job with payload of %s updated products has been scheduled for %s accounts.',
                    count($productIds),
                    $accountCount,
                ),
            ));

            return $accountCount;
        });
    }

    private function processCategoryEvents(Context $context, JobResult $result, string $parentJobId): int
    {
        $type = EventsWriter::CATEGORY_ENTITY_NAME;
        return $this->processEventBatches($context, $type, 'product_sync.changelog.category', function (
            array $categoryIds,
        ) use (
            $parentJobId,
            $result,
            $context
        ): int {
            $jobMessage = new CategorySyncMessage(Uuid::randomHex(), $parentJobId, $categoryIds, $context);
            $this->jobScheduler->schedule($jobMessage);
            $result->addMessage(new InfoMessage(
                sprintf('Job with payload of %s updated categories has been scheduled.', count($categoryIds)),
            ));

            return 1;
        });
    }

    private function processExchangeRateEvents(Context $context, JobResult $result, string $parentJobId): int
    {
        $type = EventsWriter::EXCHANGE_RATE_ENTITY_NAME;
        return $this->processEventBatches($context, $type, 'product_sync.changelog.exchange_rate', function (
            array $exchangeRateIds,
        ) use (
            $parentJobId,
            $result,
            $context
        ): int {
            if (!$exchangeRateIds) {
                return 0;
            }

            $jobMessage = new ExchangeRateSyncMessage(Uuid::randomHex(), $parentJobId, $context);
            $this->jobScheduler->schedule($jobMessage);
            $result->addMessage(new InfoMessage(
                sprintf('Exchange rate sync scheduled due to %s currency change(s).', count($exchangeRateIds)),
            ));

            return 1;
        });
    }

    private function shouldLogExtra(): bool
    {
        return $this->configProvider->isEnabledProductSyncExtraLogging();
    }

    private function logDuration(
        Context $context,
        string $message,
        float $startTime,
        array $additionalContext = [],
    ): void {
        $durationMs = (microtime(true) - $startTime) * 1000;

        $this->logger->info($message, array_merge(
            $additionalContext,
            [
                'duration_ms' => round($durationMs, 2),
                'language_id' => $context->getLanguageId(),
                'currency_id' => $context->getCurrencyId(),
            ],
        ));
    }
}
