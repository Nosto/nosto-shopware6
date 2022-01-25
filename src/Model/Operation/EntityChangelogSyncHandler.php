<?php

namespace Od\NostoIntegration\Model\Operation;

use Od\NostoIntegration\Async\EntityChangelogSyncMessage;
use Od\NostoIntegration\Async\EventsWriter;
use Od\NostoIntegration\Async\MarketingPermissionSyncMessage;
use Od\NostoIntegration\Async\OrderSyncMessage;
use Od\NostoIntegration\Async\ProductSyncMessage;
use Od\NostoIntegration\Entity\Changelog\ChangelogEntity;
use Od\Scheduler\Model\Job\GeneratingHandlerInterface;
use Od\Scheduler\Model\Job\JobHandlerInterface;
use Od\Scheduler\Model\Job\JobResult;
use Od\Scheduler\Model\JobScheduler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
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
        $context = Context::createDefaultContext();
        $this->processNewsletterSubscriberEvents($context, $message->getJobId());
        $this->processOrderEvents($context, $message->getJobId());
        $this->processProductEvents($context, $message->getJobId());

        return new JobResult();
    }

    private function processOrderEvents(Context $context, string $parentJobId)
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('entity_type', [EventsWriter::ORDER_ENTITY_NAME]));
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        $criteria->setLimit(self::BATCH_SIZE);
        $iterator = new RepositoryIterator($this->entityChangelogRepository, $context, $criteria);

        while (($events = $iterator->fetch()) !== null) {
            $orderIds = $events->map(fn(ChangelogEntity $event) => $event->getEntityId());
            $jobMessage = new OrderSyncMessage(Uuid::randomHex(), $parentJobId, $orderIds);
            $this->jobScheduler->schedule($jobMessage);
            $deleteDataSet = array_map(function ($id) {
                return ['id' => $id];
            }, array_values($events->getIds()));
            $this->entityChangelogRepository->delete($deleteDataSet, $context);
        }
    }

    private function processNewsletterSubscriberEvents(Context $context, string $parentJobId)
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('entity_type', [EventsWriter::NEWSLETTER_ENTITY_NAME]));
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        $criteria->setLimit(self::BATCH_SIZE);
        $iterator = new RepositoryIterator($this->entityChangelogRepository, $context, $criteria);

        while (($events = $iterator->fetch()) !== null) {
            $newsletterSubscriberIds = $events->map(fn(ChangelogEntity $event) => $event->getEntityId());
            $jobMessage = new MarketingPermissionSyncMessage(Uuid::randomHex(), $parentJobId, $newsletterSubscriberIds);
            $this->jobScheduler->schedule($jobMessage);
            $deleteDataSet = array_map(function ($id) {
                return ['id' => $id];
            }, array_values($events->getIds()));
            $this->entityChangelogRepository->delete($deleteDataSet, $context);
        }
    }

    private function processProductEvents(Context $context, string $parentJobId)
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('entity_type', [EventsWriter::PRODUCT_ENTITY_NAME]));
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        $criteria->setLimit(self::BATCH_SIZE);
        $iterator = new RepositoryIterator($this->entityChangelogRepository, $context, $criteria);

        while (($events = $iterator->fetch()) !== null) {
            $productIds = $events->map(fn(ChangelogEntity $event) => $event->getEntityId());
            $jobMessage = new ProductSyncMessage(Uuid::randomHex(), $parentJobId, $productIds);
            $this->jobScheduler->schedule($jobMessage);
            $deleteDataSet = array_map(function ($id) {
                return ['id' => $id];
            }, array_values($events->getIds()));
            $this->entityChangelogRepository->delete($deleteDataSet, $context);
        }
    }
}