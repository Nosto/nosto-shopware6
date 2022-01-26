<?php

namespace Od\NostoIntegration\Model\Operation;

use Od\NostoIntegration\Async\FullCatalogSyncMessage;
use Od\NostoIntegration\Async\ProductSyncMessage;
use Od\Scheduler\Model\Job\GeneratingHandlerInterface;
use Od\Scheduler\Model\Job\JobHandlerInterface;
use Od\Scheduler\Model\Job\JobResult;
use Od\Scheduler\Model\JobScheduler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;

class FullCatalogSyncHandler implements JobHandlerInterface, GeneratingHandlerInterface
{
    public const HANDLER_CODE = 'od-nosto-full-catalog-sync';
    private const BATCH_SIZE = 100;

    private EntityRepositoryInterface $productRepository;
    private JobScheduler $jobScheduler;

    public function __construct(
        EntityRepositoryInterface $productRepository,
        JobScheduler $jobScheduler
    ) {
        $this->productRepository = $productRepository;
        $this->jobScheduler = $jobScheduler;
    }

    /**
     * @param FullCatalogSyncMessage $message
     *
     * @return JobResult
     */
    public function execute(object $message): JobResult
    {
        $criteria = new Criteria();
        $criteria->setLimit(self::BATCH_SIZE);
        $context = Context::createDefaultContext();
        $repositoryIterator = new RepositoryIterator($this->productRepository, $context, $criteria);

        while (($productIds = $repositoryIterator->fetchIds()) !== null) {
            $jobMessage = new ProductSyncMessage(Uuid::randomHex(), $message->getJobId(), $productIds);
            $this->jobScheduler->schedule($jobMessage);
        }

        return new JobResult();
    }
}
