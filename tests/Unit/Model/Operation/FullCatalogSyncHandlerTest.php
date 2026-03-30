<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Model\Operation;

use Nosto\NostoIntegration\Async\CategorySyncMessage;
use Nosto\NostoIntegration\Async\FullCatalogSyncMessage;
use Nosto\NostoIntegration\Async\ProductSyncMessage;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Account;
use Nosto\NostoIntegration\Model\Operation\FullCatalogSyncHandler;
use Nosto\Scheduler\Model\Job\JobHelper;
use Nosto\Scheduler\Model\Job\JobResult;
use Nosto\Scheduler\Model\JobScheduler;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;

final class FullCatalogSyncHandlerTest extends TestCase
{
    public function testExecuteSchedulesBatchedProductAndCategoryChildJobs(): void
    {
        $context = Context::createDefaultContext();
        $message = new FullCatalogSyncMessage(Uuid::randomHex(), $context);

        $productRepository = $this->createMock(EntityRepository::class);
        $categoryRepository = $this->createMock(EntityRepository::class);
        $productRepository->method('getDefinition')->willReturn($this->createDefinition('product_test'));
        $categoryRepository->method('getDefinition')->willReturn($this->createDefinition('category_test'));

        $productCalls = 0;
        $productRepository->method('search')->willReturnCallback(
            static function (Criteria $criteria, Context $context) use (&$productCalls): EntitySearchResult {
                ++$productCalls;
                $product = new PartialEntity([
                    'id' => Uuid::randomHex(),
                    'productNumber' => 'SW-DEMO-' . $productCalls,
                ]);

                $entities = $productCalls <= 2 ? [$product] : [];

                return new EntitySearchResult(
                    PartialEntity::class,
                    count($entities),
                    new EntityCollection($entities),
                    new AggregationResultCollection(),
                    $criteria,
                    $context,
                );
            },
        );

        $categoryCalls = 0;
        $categoryRepository->method('search')->willReturnCallback(
            static function (Criteria $criteria, Context $context) use (&$categoryCalls): EntitySearchResult {
                ++$categoryCalls;
                $category = new CategoryEntity();
                $category->setId(Uuid::randomHex());
                $entities = $categoryCalls === 1 ? [$category] : [];

                return new EntitySearchResult(
                    CategoryEntity::class,
                    count($entities),
                    new EntityCollection($entities),
                    new AggregationResultCollection(),
                    $criteria,
                    $context,
                );
            },
        );

        $scheduledMessages = [];
        $jobScheduler = $this->createMock(JobScheduler::class);
        $jobScheduler->method('schedule')->willReturnCallback(
            static function (object $job) use (&$scheduledMessages): void {
                $scheduledMessages[] = $job;
            },
        );

        $jobHelperRecorder = new FullCatalogJobHelperRecorder();
        $jobHelper = new FullCatalogRecordingJobHelper($jobHelperRecorder);

        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('getBatchSize')->willReturn(1);
        $configProvider->method('isEnabledProductSyncExtraLogging')->willReturn(false);
        $configProvider->method('isEnabledMultiCurrency')->willReturn(false);

        $account = $this->createMock(Account::class);
        $account->method('getChannelId')->willReturn('channel-id');
        $account->method('getLanguageId')->willReturn('language-id');

        $accountProvider = $this->createMock(\Nosto\NostoIntegration\Model\Nosto\Account\Provider::class);
        $accountProvider->method('all')->willReturn([$account]);

        $handler = new FullCatalogSyncHandler(
            $productRepository,
            $categoryRepository,
            $jobScheduler,
            $jobHelper,
            $configProvider,
            $accountProvider,
            $this->createMock(LoggerInterface::class),
        );

        $result = $handler->execute($message);

        self::assertInstanceOf(JobResult::class, $result);
        self::assertCount(3, $scheduledMessages);
        self::assertInstanceOf(ProductSyncMessage::class, $scheduledMessages[0]);
        self::assertInstanceOf(ProductSyncMessage::class, $scheduledMessages[1]);
        self::assertInstanceOf(CategorySyncMessage::class, $scheduledMessages[2]);
        self::assertSame(['SW-DEMO-1'], array_values($scheduledMessages[0]->getProductIds()));
        self::assertSame(['SW-DEMO-2'], array_values($scheduledMessages[1]->getProductIds()));
        self::assertSame(3, $jobHelperRecorder->marks[1][1]);
        self::assertTrue($jobHelperRecorder->marks[1][2]);
    }

    private function createDefinition(string $entityName): EntityDefinition
    {
        $definition = new class extends EntityDefinition {
            public string $entityName;

            public function getEntityName(): string
            {
                return $this->entityName;
            }

            protected function defineFields(): FieldCollection
            {
                return new FieldCollection();
            }
        };
        $definition->entityName = $entityName;

        $definition->compile($this->createMock(DefinitionInstanceRegistry::class));

        return $definition;
    }
}

final class FullCatalogJobHelperRecorder
{
    /**
     * @var array<int, array{0: string, 1: int, 2: bool}>
     */
    public array $marks = [];
}

readonly class FullCatalogRecordingJobHelper extends JobHelper
{
    public function __construct(
        private FullCatalogJobHelperRecorder $recorder,
    ) {
    }

    public function markChildGenerationState(string $jobId, int $count, bool $done): void
    {
        $this->recorder->marks[] = [$jobId, $count, $done];
    }
}
