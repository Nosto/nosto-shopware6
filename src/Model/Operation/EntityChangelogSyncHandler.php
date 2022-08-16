<?php

namespace Od\NostoIntegration\Model\Operation;

use Od\NostoIntegration\Async\EntityChangelogSyncMessage;
use Od\NostoIntegration\Async\EventsWriter;
use Od\NostoIntegration\Async\MarketingPermissionSyncMessage;
use Od\NostoIntegration\Async\OrderSyncMessage;
use Od\NostoIntegration\Async\ProductSyncMessage;
use Od\NostoIntegration\Entity\Changelog\ChangelogEntity;
use Od\Scheduler\Model\Job\{GeneratingHandlerInterface, JobHandlerInterface, JobResult, Message\InfoMessage};
use Od\Scheduler\Model\JobScheduler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;

class EntityChangelogSyncHandler implements JobHandlerInterface, GeneratingHandlerInterface
{
    public const HANDLER_CODE = 'od-nosto-entity-changelog-sync';
    private const BATCH_SIZE = 100;
    private JobScheduler $jobScheduler;
    private EntityRepositoryInterface $entityChangelogRepository;

    public function __construct(
        EntityRepositoryInterface $entityChangelogRepository,
        JobScheduler $jobScheduler
    ) {
        $this->entityChangelogRepository = $entityChangelogRepository;
        $this->jobScheduler = $jobScheduler;
    }

    /**
     * @param EntityChangelogSyncMessage $message
     *
     * @return JobResult
     */
    public function execute(object $message): JobResult
    {
        $result = new JobResult();
        $context = Context::createDefaultContext();
        $this->processMarketingPermissionEvents($context, $result, $message->getJobId());
        $this->processNewOrderEvents($context, $result, $message->getJobId());
        $this->processUpdatedOrderEvents($context, $result, $message->getJobId());
        $this->processProductEvents($context, $result, $message->getJobId());

        return $result;
    }

    private function processMarketingPermissionEvents(Context $context, JobResult $result, string $parentJobId)
    {
        $type = EventsWriter::NEWSLETTER_ENTITY_NAME;
        $this->processEventBatches($context, $type, function (array $subscriberIds) use ($parentJobId, $result) {
            $jobMessage = new MarketingPermissionSyncMessage(Uuid::randomHex(), $parentJobId, $subscriberIds);
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
            $ids = $events->map(fn(ChangelogEntity $event) => $event->getEntityId());
            $processCallback($ids);
            $deleteDataSet = array_map(function ($id) {
                return ['id' => $id];
            }, array_values($events->getIds()));
            $this->entityChangelogRepository->delete($deleteDataSet, $context);
        }
    }

    private function processNewOrderEvents(Context $context, JobResult $result, string $parentJobId)
    {
        $type = EventsWriter::ORDER_ENTITY_PLACED_NAME;
        $this->processEventBatches($context, $type, function (array $orderIds) use ($parentJobId, $result) {
            $jobMessage = new OrderSyncMessage(
                Uuid::randomHex(),
                $parentJobId,
                $orderIds,
                [],
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
        $this->processEventBatches($context, $type, function (array $orderIds) use ($parentJobId, $result) {
            $jobMessage = new OrderSyncMessage(
                Uuid::randomHex(),
                $parentJobId,
                [],
                $orderIds,
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
        $this->processEventBatches($context, $type, function (array $productIds) use ($parentJobId, $result) {
            $jobMessage = new ProductSyncMessage(Uuid::randomHex(), $parentJobId, $productIds);
            $this->jobScheduler->schedule($jobMessage);
            $result->addMessage(new InfoMessage(
                \sprintf('Job with payload of %s updated products has been scheduled.', count($productIds))
            ));
        });
    }
}
