<?php

namespace Nosto\NostoIntegration\Model\Operation;

use Nosto\NostoIntegration\Async\EntityChangelogSyncMessage;
use Nosto\NostoIntegration\Async\EventsWriter;
use Nosto\NostoIntegration\Async\MarketingPermissionSyncMessage;
use Nosto\NostoIntegration\Async\OrderSyncMessage;
use Nosto\NostoIntegration\Async\ProductSyncMessage;
use Nosto\NostoIntegration\Entity\Changelog\ChangelogEntity;
use Nosto\Scheduler\Model\Job\{GeneratingHandlerInterface, JobHandlerInterface, JobResult, Message\InfoMessage};
use Nosto\Scheduler\Model\JobScheduler;
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

    private JobScheduler $jobScheduler;

    private EntityRepository $entityChangelogRepository;

    public function __construct(
        EntityRepository $entityChangelogRepository,
        JobScheduler $jobScheduler
    ) {
        $this->entityChangelogRepository = $entityChangelogRepository;
        $this->jobScheduler = $jobScheduler;
    }

    /**
     * @param EntityChangelogSyncMessage $message
     */
    public function execute(object $message): JobResult
    {
        $result = new JobResult();
        $this->processMarketingPermissionEvents($message->getContext(), $result, $message->getJobId());
        $this->processNewOrderEvents($message->getContext(), $result, $message->getJobId());
        $this->processUpdatedOrderEvents($message->getContext(), $result, $message->getJobId());
        $this->processProductEvents($message->getContext(), $result, $message->getJobId());

        return $result;
    }

    private function processMarketingPermissionEvents(Context $context, JobResult $result, string $parentJobId)
    {
        $type = EventsWriter::NEWSLETTER_ENTITY_NAME;
        $this->processEventBatches($context, $type, function (array $subscriberIds) use ($parentJobId, $result, $context) {
            $jobMessage = new MarketingPermissionSyncMessage(Uuid::randomHex(), $parentJobId, $subscriberIds, $context);
            $this->jobScheduler->schedule($jobMessage);
            $result->addMessage(new InfoMessage(
                \sprintf(
                    'Job with payload of %s marketing permission updates has been scheduled.',
                    count($subscriberIds)
                )
            ));
        });
    }

    private function processEventBatches(Context $context, string $entityType, callable $processCallback)
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('entityType', $entityType));
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        $criteria->setLimit(self::BATCH_SIZE);

        $iterator = new RepositoryIterator($this->entityChangelogRepository, $context, $criteria);

        while (($events = $iterator->fetch()) !== null) {
            $ids = $entityType === ProductDefinition::ENTITY_NAME ?
                $events->reduce(function ($result, $event) {
                    $result[$event->getEntityId()] = $event->getProductNumber();
                    return $result;
                }, []) :
                $events->map(fn (ChangelogEntity $event) => $event->getEntityId());

            $processCallback($ids);
            $deleteDataSet = array_map(function ($id) {
                return [
                    'id' => $id,
                ];
            }, array_values($events->getIds()));
            $this->entityChangelogRepository->delete($deleteDataSet, $context);
        }
    }

    private function processNewOrderEvents(Context $context, JobResult $result, string $parentJobId)
    {
        $type = EventsWriter::ORDER_ENTITY_PLACED_NAME;
        $this->processEventBatches($context, $type, function (array $orderIds) use ($parentJobId, $result, $context) {
            $jobMessage = new OrderSyncMessage(
                Uuid::randomHex(),
                $parentJobId,
                $orderIds,
                [],
                $context,
                'New Order Sync Operation'
            );
            $this->jobScheduler->schedule($jobMessage);
            $result->addMessage(new InfoMessage(
                \sprintf('Job with payload of %s new orders has been scheduled.', count($orderIds))
            ));
        });
    }

    private function processUpdatedOrderEvents(Context $context, JobResult $result, string $parentJobId)
    {
        $type = EventsWriter::ORDER_ENTITY_UPDATED_NAME;
        $this->processEventBatches($context, $type, function (array $orderIds) use ($parentJobId, $result, $context) {
            $jobMessage = new OrderSyncMessage(
                Uuid::randomHex(),
                $parentJobId,
                [],
                $orderIds,
                $context,
                'Updated Order Sync Operation'
            );
            $this->jobScheduler->schedule($jobMessage);
            $result->addMessage(new InfoMessage(
                \sprintf('Job with payload of %s updated orders has been scheduled.', count($orderIds))
            ));
        });
    }

    private function processProductEvents(Context $context, JobResult $result, string $parentJobId)
    {
        $type = EventsWriter::PRODUCT_ENTITY_NAME;
        $this->processEventBatches($context, $type, function (array $productIds) use ($parentJobId, $result, $context) {
            $jobMessage = new ProductSyncMessage(Uuid::randomHex(), $parentJobId, $productIds, $context);
            $this->jobScheduler->schedule($jobMessage);
            $result->addMessage(new InfoMessage(
                \sprintf('Job with payload of %s updated products has been scheduled.', count($productIds))
            ));
        });
    }
}
