<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Operation;

use Nosto\NostoIntegration\Async\CategorySyncMessage;
use Nosto\NostoIntegration\Async\EntityChangelogSyncMessage;
use Nosto\NostoIntegration\Async\EventsWriter;
use Nosto\NostoIntegration\Async\MarketingPermissionSyncMessage;
use Nosto\NostoIntegration\Async\OrderSyncMessage;
use Nosto\NostoIntegration\Async\ProductSyncMessage;
use Nosto\NostoIntegration\Entity\Changelog\ChangelogEntity;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Account\Provider as AccountProvider;
use Nosto\Scheduler\Model\Job\{GeneratingHandlerInterface, JobHandlerInterface, JobResult, Message\InfoMessage};
use Nosto\Scheduler\Model\JobScheduler;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
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
        $this->processMarketingPermissionEvents($message->getContext(), $result, $message->getJobId());
        $this->processNewOrderEvents($message->getContext(), $result, $message->getJobId());
        $this->processUpdatedOrderEvents($message->getContext(), $result, $message->getJobId());
        $this->processProductEvents($message->getContext(), $result, $message->getJobId());
        $this->processCategoryEvents($message->getContext(), $result, $message->getJobId());

        if ($shouldLogExtra && $syncStartedAt !== null) {
            $this->logDuration(
                $message->getContext(),
                'product_sync.changelog.execute',
                $syncStartedAt,
            );
        }

        return $result;
    }

    private function processMarketingPermissionEvents(Context $context, JobResult $result, string $parentJobId): void
    {
        $type = EventsWriter::NEWSLETTER_ENTITY_NAME;
        $this->processEventBatches($context, $type, function (array $subscriberIds) use (
            $parentJobId,
            $result,
            $context
        ) {
            $jobMessage = new MarketingPermissionSyncMessage(Uuid::randomHex(), $parentJobId, $subscriberIds, $context);
            $this->jobScheduler->schedule($jobMessage);
            $result->addMessage(new InfoMessage(
                sprintf(
                    'Job with payload of %s marketing permission updates has been scheduled.',
                    count($subscriberIds),
                ),
            ));
        });
    }

    private function processEventBatches(
        Context $context,
        string $entityType,
        string $metricPrefix,
        callable $processCallback,
    ): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('entityType', $entityType));
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        $criteria->setLimit(self::BATCH_SIZE);

        $iterator = new RepositoryIterator($this->entityChangelogRepository, $context, $criteria);
        $shouldLogExtra = $this->shouldLogExtra();
        $batchIndex = 0;
        $eventCount = 0;
        $payloadCount = 0;
        $iteratorStartedAt = $shouldLogExtra ? microtime(true) : null;

        while (($events = $iterator->fetch()) !== null) {
            ++$batchIndex;
            $batchStartedAt = $shouldLogExtra ? microtime(true) : null;
            $ids = $entityType === ProductDefinition::ENTITY_NAME || $entityType === 'order_placed' ?
                $events->reduce(function ($result, $event) {
                    $result[$event->getEntityId()] = $event->getProductNumber();
                    return $result;
                }, []) :
                $events->map(fn (ChangelogEntity $event) => $event->getEntityId());

            $batchEventCount = $events->count();
            $batchPayloadCount = count($ids);
            $eventCount += $batchEventCount;
            $payloadCount += $batchPayloadCount;

            $processCallback($ids);
            $deleteDataSet = array_map(function ($id) {
                return [
                    'id' => $id,
                ];
            }, array_values($events->getIds()));
            $deleteStartedAt = $shouldLogExtra ? microtime(true) : null;
            $this->entityChangelogRepository->delete($deleteDataSet, $context);

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
    }

    private function processNewOrderEvents(Context $context, JobResult $result, string $parentJobId): void
    {
        $type = EventsWriter::ORDER_ENTITY_PLACED_NAME;
        $this->processEventBatches($context, $type, function (array $orderIds) use ($parentJobId, $result, $context) {
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
        });
    }

    private function processUpdatedOrderEvents(Context $context, JobResult $result, string $parentJobId): void
    {
        $type = EventsWriter::ORDER_ENTITY_UPDATED_NAME;
        $this->processEventBatches($context, $type, function (array $orderIds) use ($parentJobId, $result, $context) {
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
        });
    }

    private function processProductEvents(Context $context, JobResult $result, string $parentJobId): void
    {
        $type = EventsWriter::PRODUCT_ENTITY_NAME;
        $this->processEventBatches($context, $type, function (array $productIds) use ($parentJobId, $result, $context) {
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
            }
            $result->addMessage(new InfoMessage(
                sprintf(
                    'Job with payload of %s updated products has been scheduled for %s accounts.',
                    count($productIds),
                    count($this->accountProvider->all($context)),
                ),
            ));
        });
    }

    private function processCategoryEvents(Context $context, JobResult $result, string $parentJobId): void
    {
        $type = EventsWriter::CATEGORY_ENTITY_NAME;
        $this->processEventBatches($context, $type, function (array $categoryIds) use (
            $parentJobId,
            $result,
            $context
        ): void {
            $jobMessage = new CategorySyncMessage(Uuid::randomHex(), $parentJobId, $categoryIds, $context);
            $this->jobScheduler->schedule($jobMessage);
            $result->addMessage(new InfoMessage(
                sprintf('Job with payload of %s updated categories has been scheduled.', count($categoryIds)),
            ));
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
