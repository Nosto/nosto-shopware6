<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Operation;

use Nosto\NostoIntegration\Async\CategorySyncMessage;
use Nosto\NostoIntegration\Async\FullCatalogSyncMessage;
use Nosto\NostoIntegration\Async\ProductSyncMessage;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\Scheduler\Model\Job\GeneratingHandlerInterface;
use Nosto\Scheduler\Model\Job\JobHandlerInterface;
use Nosto\Scheduler\Model\Job\JobResult;
use Nosto\Scheduler\Model\Job\Message\InfoMessage;
use Nosto\Scheduler\Model\JobScheduler;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\{EqualsFilter, NotFilter};
use Shopware\Core\Framework\Uuid\Uuid;

class FullCatalogSyncHandler implements JobHandlerInterface, GeneratingHandlerInterface
{
    public const HANDLER_CODE = 'nosto-integration-full-catalog-sync';

    private const BATCH_SIZE = 150;

    public function __construct(
        private readonly EntityRepository $productRepository,
        private readonly EntityRepository $categoryRepository,
        private readonly JobScheduler $jobScheduler,
        private readonly ConfigProvider $configProvider,
    ) {
    }

    /**
     * @param FullCatalogSyncMessage $message
     */
    public function execute(object $message): JobResult
    {
        $size = $this->configProvider->getBatchSize();
        $result = new JobResult();
        $criteriaProduct = new Criteria();
        $criteriaProduct->setLimit($size ? $size : self::BATCH_SIZE);
        $productRepositoryIterator = new RepositoryIterator(
            $this->productRepository,
            $message->getContext(),
            $criteriaProduct,
        );
        $result->addMessage(new InfoMessage('Child job generation started.'));

        while (($products = $productRepositoryIterator->fetch()) !== null) {
            $ids = $this->getProductIdsForMessage($products->getEntities());
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

        $criteriaCategory = new Criteria();
        $criteriaCategory->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [
            new EqualsFilter('parentId', null),
        ]));
        $criteriaCategory->setLimit(self::BATCH_SIZE);
        $categoryRepositoryIterator = new RepositoryIterator(
            $this->categoryRepository,
            $message->getContext(),
            $criteriaCategory,
        );
        while (($categories = $categoryRepositoryIterator->fetch()) !== null) {
            $ids = $this->getCategoryIdsForMessage($categories->getEntities());
            $this->jobScheduler->schedule(
                new CategorySyncMessage(
                    Uuid::randomHex(),
                    $message->getJobId(),
                    $ids,
                    $message->getContext(),
                ),
            );
            $result->addMessage(
                new InfoMessage('Job with payload of: ' . count($ids) . ' categories has been scheduled.'),
            );
        }

        return $result;
    }

    /**
     * @return array<string, string>
     */
    private function getProductIdsForMessage(EntityCollection $products): array
    {
        $data = [];
        foreach ($products as $product) {
            $data[$product->getId()] = $product->getProductNumber();
        }
        return $data;
    }

    /**
     * @return array<string, string>
     */
    private function getCategoryIdsForMessage(EntityCollection $categories): array
    {
        $data = [];
        foreach ($categories as $category) {
            $data[$category->getId()] = $category->getId();
        }
        return $data;
    }
}
