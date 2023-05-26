<?php

namespace Od\NostoIntegration\Model\Operation;

use Od\NostoIntegration\Async\FullCatalogSyncMessage;
use Od\NostoIntegration\Async\ProductSyncMessage;
use Od\Scheduler\Model\Job\GeneratingHandlerInterface;
use Od\Scheduler\Model\Job\JobHandlerInterface;
use Od\Scheduler\Model\Job\JobResult;
use Od\Scheduler\Model\Job\Message\InfoMessage;
use Od\Scheduler\Model\JobScheduler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;

class FullCatalogSyncHandler implements JobHandlerInterface, GeneratingHandlerInterface
{
    public const HANDLER_CODE = 'od-nosto-full-catalog-sync';
    private const BATCH_SIZE = 100;

    private EntityRepository $productRepository;
    private JobScheduler $jobScheduler;

    public function __construct(
        EntityRepository $productRepository,
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
        $result = new JobResult();
        $criteria = new Criteria();
        $criteria->setLimit(self::BATCH_SIZE);
        $repositoryIterator = new RepositoryIterator($this->productRepository, $message->getContext(), $criteria);
        $result->addMessage(new InfoMessage('Child job generation started.'));

        while (($products = $repositoryIterator->fetch()) !== null) {
            if (is_int($products)) {
                continue;
            }
            $jobMessage = new ProductSyncMessage(Uuid::randomHex(), $message->getJobId(), $this->getIdsForMessage($products->getEntities()), $message->getContext());
            $this->jobScheduler->schedule($jobMessage);
            $result->addMessage(new InfoMessage(
                \sprintf('Job with payload of products has been scheduled.')
            ));
        }

        return $result;
    }

    private function getIdsForMessage(EntityCollection $products): array
    {
        $data = [];
        foreach ($products as $product) {
            $data[$product->getId()] = $product->getProductNumber();
        }
        return $data;
    }
}
