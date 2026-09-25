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
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Account\Provider as AccountProvider;
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
use Shopware\Core\Framework\Uuid\Uuid;

class EntityChangelogSyncHandler implements JobHandlerInterface, GeneratingHandlerInterface
{
    public const HANDLER_CODE = 'nosto-integration-entity-changelog-sync';

    private const BATCH_SIZE = 100;

    public function __construct(
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
        // Everything pending when this run starts. Rows written while it runs get a higher id and
        // are left for the next run rather than being deleted unprocessed.
        $watermark = $this->fetchWatermark($entityType);
        if ($watermark === null) {
            return 0;
        }

        $shouldLogExtra = $this->shouldLogExtra();
        $batchIndex = 0;
        $eventCount = 0;
        $payloadCount = 0;
        $scheduledChildCount = 0;
        $iteratorStartedAt = $shouldLogExtra ? microtime(true) : null;
        $previousEntityIds = null;

        while (($rows = $this->fetchOldestEventBatch($entityType, $watermark)) !== []) {
            $entityIds = $this->collectEntityIds($rows);
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
            $ids = $this->getPayloadFromBatch($entityType, $entityIds, $watermark);

            $batchEventCount = count($rows);
            $batchPayloadCount = count($ids);
            $eventCount += $batchEventCount;
            $payloadCount += $batchPayloadCount;

            $scheduledChildCount += $processCallback($ids);
            $deleteStartedAt = $shouldLogExtra ? microtime(true) : null;
            $this->deleteHandledEvents($entityType, $entityIds, $watermark);

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
     * Highest changelog id for this entity type, captured once per run.
     *
     * Shopware generates UUIDv7, whose leading bits are a millisecond timestamp, so the primary key
     * is already in creation order. Everything at or below this id existed when the run started;
     * anything written afterwards sorts above it and is left alone.
     */
    private function fetchWatermark(string $entityType): ?string
    {
        $watermark = $this->connection->fetchOne(
            'SELECT LOWER(HEX(MAX(`id`))) FROM `' . ChangelogDefinition::ENTITY_NAME . '`
             WHERE `entity_type` = :entityType',
            [
                'entityType' => $entityType,
            ],
        );

        return is_string($watermark) ? $watermark : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchOldestEventBatch(string $entityType, string $watermark): array
    {
        return $this->connection->executeQuery(
            'SELECT LOWER(HEX(`entity_id`)) AS entity_id
             FROM `' . ChangelogDefinition::ENTITY_NAME . '`
             WHERE `entity_type` = :entityType AND `id` <= :watermark
             ORDER BY `id`
             LIMIT ' . self::BATCH_SIZE,
            [
                'entityType' => $entityType,
                'watermark' => Uuid::fromHexToBytes($watermark),
            ],
        )->fetchAllAssociative();
    }

    /**
     * Collapses a batch to its distinct entities, so an entity written several times is scheduled
     * once.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private function collectEntityIds(array $rows): array
    {
        $entityIds = [];
        foreach ($rows as $row) {
            $entityId = $row['entity_id'] ?? null;
            if (is_string($entityId)) {
                $entityIds[$entityId] = true;
            }
        }

        return array_keys($entityIds);
    }

    /**
     * @param list<string> $entityIds
     * @return array<string, string|null>|list<string>
     */
    private function getPayloadFromBatch(string $entityType, array $entityIds, string $watermark): array
    {
        if ($entityType !== ProductDefinition::ENTITY_NAME
            && $entityType !== EventsWriter::ORDER_ENTITY_PLACED_NAME
        ) {
            return $entityIds;
        }

        return $this->fetchLatestProductNumbers($entityType, $entityIds, $watermark);
    }

    /**
     * Reads each entity's most recent product number across every pending row, not only the rows in
     * this batch. Taking it from the batch alone would send a stale identifier whenever an entity
     * has more pending rows than the batch size.
     *
     * @param list<string> $entityIds
     * @return array<string, string|null>
     */
    private function fetchLatestProductNumbers(string $entityType, array $entityIds, string $watermark): array
    {
        if ($entityIds === []) {
            return [];
        }

        $rows = $this->connection->executeQuery(
            'SELECT LOWER(HEX(c.`entity_id`)) AS entity_id, c.`product_number` AS productNumber
             FROM `' . ChangelogDefinition::ENTITY_NAME . '` c
             INNER JOIN (
                 SELECT `entity_id`, MAX(`id`) AS latest_id
                 FROM `' . ChangelogDefinition::ENTITY_NAME . '`
                 WHERE `entity_type` = :entityType
                   AND `id` <= :watermark
                   AND `entity_id` IN (:entityIds)
                 GROUP BY `entity_id`
             ) latest ON latest.`entity_id` = c.`entity_id` AND latest.`latest_id` = c.`id`',
            [
                'entityType' => $entityType,
                'watermark' => Uuid::fromHexToBytes($watermark),
                'entityIds' => Uuid::fromHexToBytesList($entityIds),
            ],
            [
                'entityIds' => ArrayParameterType::BINARY,
            ],
        )->fetchAllAssociative();

        $payload = [];
        foreach ($rows as $row) {
            $entityId = $row['entity_id'] ?? null;
            if (!is_string($entityId)) {
                continue;
            }

            $productNumber = $row['productNumber'] ?? null;
            $payload[$entityId] = is_string($productNumber) ? $productNumber : null;
        }

        return $payload;
    }

    /**
     * Removes every pending row of the handled entities, including rows this batch did not read, so
     * an entity written many times is scheduled once rather than once per batch it appears in. The
     * watermark keeps rows written during the run untouched.
     *
     * @param list<string> $entityIds
     */
    private function deleteHandledEvents(string $entityType, array $entityIds, string $watermark): void
    {
        if ($entityIds === []) {
            return;
        }

        $this->connection->executeStatement(
            'DELETE FROM `' . ChangelogDefinition::ENTITY_NAME . '`
             WHERE `entity_type` = :entityType
               AND `id` <= :watermark
               AND `entity_id` IN (:entityIds)',
            [
                'entityType' => $entityType,
                'watermark' => Uuid::fromHexToBytes($watermark),
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
