<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Operation;

use Nosto\NostoIntegration\Async\FullCatalogSyncMessage;
use Nosto\NostoIntegration\Async\ProductSyncMessage;
use Nosto\Scheduler\Model\Job\GeneratingHandlerInterface;
use Nosto\Scheduler\Model\Job\JobHandlerInterface;
use Nosto\Scheduler\Model\Job\JobResult;
use Nosto\Scheduler\Model\Job\Message\InfoMessage;
use Nosto\Scheduler\Model\JobScheduler;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;

class FullCatalogSyncHandler implements JobHandlerInterface, GeneratingHandlerInterface
{
    public const HANDLER_CODE = 'nosto-integration-full-catalog-sync';

    private const BATCH_SIZE = 150;

    public function __construct(
        private readonly EntityRepository $productRepository,
        private readonly JobScheduler $jobScheduler,
    ) {
    }

    /**
     * @param FullCatalogSyncMessage $message
     */
    public function execute(object $message): JobResult
    {
        $result = new JobResult();
        $criteria = new Criteria();
        $criteria->setLimit(self::BATCH_SIZE);
        $repositoryIterator = new RepositoryIterator($this->productRepository, $message->getContext(), $criteria);
        $result->addMessage(new InfoMessage('Child job generation started.'));

        while (($products = $repositoryIterator->fetch()) !== null) {
            $ids = $this->getIdsForMessage($products->getEntities());
            $this->jobScheduler->schedule(
                new ProductSyncMessage(
                    Uuid::randomHex(),
                    $message->getJobId(),
                    $ids,
                    $message->getContext(),
                ),
            );
            $result->addMessage(
                new InfoMessage('Job with payload of: ' . count($ids) . ' products has been scheduled.'),
            );
        }

        return $result;
    }

    /**
     * @return array<string, string>
     */
    private function getIdsForMessage(EntityCollection $products): array
    {
        $data = [];
        foreach ($products as $product) {
            $data[$product->getId()] = $product->getProductNumber();
        }
        return $data;
    }
}
