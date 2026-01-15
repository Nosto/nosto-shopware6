<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Operation;

use Nosto\NostoIntegration\Async\CategorySyncMessage;
use Nosto\NostoIntegration\Async\FullCatalogSyncMessage;
use Nosto\NostoIntegration\Async\ProductSyncMessage;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Account\Provider as AccountProvider;
use Nosto\Scheduler\Model\Job\GeneratingHandlerInterface;
use Nosto\Scheduler\Model\Job\JobHandlerInterface;
use Nosto\Scheduler\Model\Job\JobResult;
use Nosto\Scheduler\Model\Job\Message\InfoMessage;
use Nosto\Scheduler\Model\JobScheduler;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\{EqualsFilter, NotFilter};
use Shopware\Core\Framework\Uuid\Uuid;
use Nosto\NostoIntegration\Utils\NostoCriteriaFactory;

class FullCatalogSyncHandler implements JobHandlerInterface, GeneratingHandlerInterface
{
    public const HANDLER_CODE = 'nosto-integration-full-catalog-sync';

    private const BATCH_SIZE = 150;

    public function __construct(
        private readonly EntityRepository $productRepository,
        private readonly EntityRepository $categoryRepository,
        private readonly JobScheduler $jobScheduler,
        private readonly ConfigProvider $configProvider,
        private readonly AccountProvider $accountProvider,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param FullCatalogSyncMessage $message
     */
    public function execute(object $message): JobResult
    {
        $context = $message->getContext();
        $size = $this->configProvider->getBatchSize();
        $shouldLogExtra = $this->shouldLogExtra();
        $syncStartedAt = $shouldLogExtra ? microtime(true) : null;
        $result = new JobResult();
        $criteriaProduct = NostoCriteriaFactory::create();
        NostoCriteriaFactory::setTitle($criteriaProduct, 'product_sync.full_catalog.products');
        $criteriaProduct->setLimit($size ? $size : self::BATCH_SIZE);
        $productRepositoryIterator = new RepositoryIterator(
            $this->productRepository,
            $message->getContext(),
            $criteriaProduct,
        );
        $result->addMessage(new InfoMessage('Child job generation started.'));

        $productBatchCount = 0;
        $productCount = 0;
        $productsStartedAt = $shouldLogExtra ? microtime(true) : null;
        $accounts = $this->accountProvider->all($context);

        while (($products = $productRepositoryIterator->fetch()) !== null) {
            ++$productBatchCount;
            $batchStartedAt = $shouldLogExtra ? microtime(true) : null;
            $ids = $this->getProductIdsForMessage($products->getEntities());
            $batchSize = count($ids);
            $productCount += $batchSize;
            foreach ($accounts as $account) {
                $this->jobScheduler->schedule(
                    new ProductSyncMessage(
                        Uuid::randomHex(),
                        $message->getJobId(),
                        $ids,
                        $message->getContext(),
                        null,
                        $account->getChannelId(),
                        $account->getLanguageId(),
                    ),
                );
            }
            $result->addMessage(
                new InfoMessage(
                    sprintf(
                        'Job with payload of: %s products has been scheduled for %s accounts.',
                        count($ids),
                        count($accounts),
                    ),
                ),
            );
            if ($shouldLogExtra && $batchStartedAt !== null) {
                $this->logDuration(
                    $context,
                    'product_sync.full_catalog.products.batch',
                    $batchStartedAt,
                    [
                        'batch_index' => $productBatchCount,
                        'batch_size' => $batchSize,
                    ],
                );
            }
        }
        if ($shouldLogExtra && $productsStartedAt !== null && $productBatchCount > 0) {
            $this->logDuration(
                $context,
                'product_sync.full_catalog.products',
                $productsStartedAt,
                [
                    'batch_count' => $productBatchCount,
                    'product_count' => $productCount,
                ],
            );
        }

        $criteriaCategory = NostoCriteriaFactory::create();
        NostoCriteriaFactory::setTitle($criteriaCategory, 'product_sync.full_catalog.categories');
        $criteriaCategory->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [
            new EqualsFilter('parentId', null),
        ]));
        $criteriaCategory->setLimit(self::BATCH_SIZE);
        $categoryRepositoryIterator = new RepositoryIterator(
            $this->categoryRepository,
            $message->getContext(),
            $criteriaCategory,
        );
        $categoryBatchCount = 0;
        $categoryCount = 0;
        $categoriesStartedAt = $shouldLogExtra ? microtime(true) : null;
        while (($categories = $categoryRepositoryIterator->fetch()) !== null) {
            ++$categoryBatchCount;
            $batchStartedAt = $shouldLogExtra ? microtime(true) : null;
            $ids = $this->getCategoryIdsForMessage($categories->getEntities());
            $batchSize = count($ids);
            $categoryCount += $batchSize;
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
            if ($shouldLogExtra && $batchStartedAt !== null) {
                $this->logDuration(
                    $context,
                    'product_sync.full_catalog.categories.batch',
                    $batchStartedAt,
                    [
                        'batch_index' => $categoryBatchCount,
                        'batch_size' => $batchSize,
                    ],
                );
            }
        }
        if ($shouldLogExtra && $categoriesStartedAt !== null && $categoryBatchCount > 0) {
            $this->logDuration(
                $context,
                'product_sync.full_catalog.categories',
                $categoriesStartedAt,
                [
                    'batch_count' => $categoryBatchCount,
                    'category_count' => $categoryCount,
                ],
            );
        }

        if ($shouldLogExtra && $syncStartedAt !== null) {
            $this->logDuration(
                $context,
                'product_sync.full_catalog.execute',
                $syncStartedAt,
                [
                    'product_count' => $productCount,
                    'category_count' => $categoryCount,
                ],
            );
        }

        return $result;
    }

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
