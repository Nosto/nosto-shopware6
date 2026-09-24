<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Operation;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Nosto\NostoIntegration\Async\CategorySyncMessage;
use Nosto\NostoIntegration\Async\EntityChangelogSyncMessage;
use Nosto\NostoIntegration\Async\EventsWriter;
use Nosto\NostoIntegration\Async\ExchangeRateSyncMessage;
use Nosto\NostoIntegration\Async\MarketingPermissionSyncMessage;
use Nosto\NostoIntegration\Async\OrderSyncMessage;
use Nosto\NostoIntegration\Async\ProductSyncMessage;
use Nosto\NostoIntegration\Entity\Changelog\ChangelogDefinition;
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
        private readonly Connection $connection,
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
        $previousEntityIds = null;

        // Each batch takes the oldest pending rows, collapses them per entity so an entity written
        // many times is only scheduled once, and then removes every row of those entities. The next
        // batch is therefore always at the start of the result set: paginating with an offset here
        // would skip one batch for every batch handled.
        while (($batch = $this->fetchOldestEventBatch($entityType, $context)) !== []) {
            $entityIds = array_keys($batch);
            sort($entityIds);

            // Safety net: if a batch survives its delete, stop instead of looping over it forever.
            if ($previousEntityIds === $entityIds) {
                $this->logger->error(
                    'Nosto: changelog batch was still present after deletion, aborting to avoid an endless loop.',
                    [
                        'entity_type' => $entityType,
                        'batch_size' => count($entityIds),
                    ],
                );

                break;
            }

            $previousEntityIds = $entityIds;

            ++$batchIndex;
            $batchStartedAt = $shouldLogExtra ? microtime(true) : null;
            $ids = $this->getPayloadFromBatch($entityType, $batch);

            $batchPayloadCount = count($ids);
            $payloadCount += $batchPayloadCount;

            $scheduledChildCount += $processCallback($ids);
            $deleteStartedAt = $shouldLogExtra ? microtime(true) : null;
            // Counts every row removed, which includes rows of these entities beyond the ones read.
            $batchEventCount = $this->deleteEventsByEntityIds($entityType, array_keys($batch));
            $eventCount += $batchEventCount;

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
     * Reads the oldest pending rows and collapses them per entity, so an entity that was written
     * many times is only scheduled once.
     *
     * The grouping happens here rather than in SQL on purpose. A GROUP BY whose ORDER BY is an
     * aggregate cannot push the LIMIT down, so the database would build and sort every pending
     * group on every batch - spilling large result sets to an on-disk temporary table - instead of
     * reading the first rows of an index and stopping.
     *
     * @return array<string, string|null> entity id => product number
     */
    private function fetchOldestEventBatch(string $entityType, Context $context): array
    {
        $criteria = NostoCriteriaFactory::create('product_sync.changelog.batch');
        $criteria->addFilter(new EqualsFilter('entityType', $entityType));
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));
        $criteria->setLimit(self::BATCH_SIZE);

        $batch = [];
        /** @var ChangelogEntity $event */
        foreach ($this->entityChangelogRepository->search($criteria, $context) as $event) {
            // Rows arrive oldest first, so the last value seen is the most recent one known.
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
     * Removes every row of the given entities, including rows that were not read, so a processed
     * entity cannot reappear in a later batch.
     *
     * @param list<string> $entityIds
     * @return int number of rows removed
     */
    private function deleteEventsByEntityIds(string $entityType, array $entityIds): int
    {
        if ($entityIds === []) {
            return 0;
        }

        return (int) $this->connection->executeStatement(
            'DELETE FROM `' . ChangelogDefinition::ENTITY_NAME . '`
             WHERE `entity_type` = :entityType
             AND `entity_id` IN (:entityIds)',
            [
                'entityType' => $entityType,
                'entityIds' => Uuid::fromHexToBytesList($entityIds),
            ],
            [
                'entityIds' => ArrayParameterType::BINARY,
            ],
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
