<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Model\Operation;

use Nosto\NostoIntegration\Async\CategorySyncMessage;
use Nosto\NostoIntegration\Async\FullCatalogSyncMessage;
use Nosto\NostoIntegration\Async\ProductSyncMessage;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Account;
use Nosto\NostoIntegration\Model\Nosto\Account\Provider as AccountProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\ProductHelper;
use Nosto\NostoIntegration\Model\Operation\FullCatalogSyncHandler;
use Nosto\Scheduler\Model\Job\JobHelper;
use Nosto\Scheduler\Model\Job\JobResult;
use Nosto\Scheduler\Model\JobScheduler;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Uuid\Uuid;

final class FullCatalogSyncHandlerTest extends TestCase
{
    public function testExecuteSchedulesBatchedProductAndCategoryChildJobs(): void
    {
        $context = Context::createDefaultContext();
        $message = new FullCatalogSyncMessage(Uuid::randomHex(), $context);

        $categoryRepository = $this->createMock(EntityRepository::class);
        $categoryRepository->method('getDefinition')->willReturn($this->createDefinition('category_test'));

        $productId1 = Uuid::randomHex();
        $productId2 = Uuid::randomHex();
        $productCalls = 0;

        // Products are paged with the DAL's own keyset iterator, one id per batch here.
        $productRepository = $this->createMock(EntityRepository::class);
        $productRepository->method('getDefinition')->willReturn($this->createDefinition('product_test'));
        $productRepository->method('searchIds')->willReturnCallback(
            static function (Criteria $criteria, Context $context) use (
                &$productCalls,
                $productId1,
                $productId2
            ): IdSearchResult {
                ++$productCalls;
                $ids = match ($productCalls) {
                    1 => [$productId1],
                    2 => [$productId2],
                    default => [],
                };

                return IdSearchResult::fromIds($ids, $criteria, $context);
            },
        );

        // The id -> productNumber lookup is the ProductHelper's job, not the handler's.
        $productHelper = $this->createMock(ProductHelper::class);
        $productHelper->method('loadOrderNumberMapping')->willReturnCallback(
            static function (array $ids) use ($productId1, $productId2): array {
                $numbers = [
                    $productId1 => 'SW-DEMO-1',
                    $productId2 => 'SW-DEMO-2',
                ];
                $mapping = [];
                foreach ($ids as $id) {
                    if (isset($numbers[$id])) {
                        $mapping[$id] = $numbers[$id];
                    }
                }

                return $mapping;
            },
        );

        $categoryCalls = 0;
        $categoryId = Uuid::randomHex();
        $categoryRepository->method('searchIds')->willReturnCallback(
            static function (Criteria $criteria, Context $context) use (&$categoryCalls, $categoryId): IdSearchResult {
                ++$categoryCalls;

                return IdSearchResult::fromIds($categoryCalls === 1 ? [$categoryId] : [], $criteria, $context);
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

        $accountProvider = $this->createMock(AccountProvider::class);
        $accountProvider->method('all')->willReturn([$account]);

        $handler = new FullCatalogSyncHandler(
            $productRepository,
            $categoryRepository,
            $jobScheduler,
            $jobHelper,
            $configProvider,
            $accountProvider,
            $productHelper,
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
        self::assertSame([$categoryId], array_values($scheduledMessages[2]->getCategoryIds()));
        self::assertSame(3, $jobHelperRecorder->marks[1][1]);
        self::assertTrue($jobHelperRecorder->marks[1][2]);
    }

    private function createDefinition(string $entityName): EntityDefinition
    {
        $definition = new class($entityName) extends EntityDefinition {
            public function __construct(
                private readonly string $entityName,
            ) {
            }

            public function getEntityName(): string
            {
                return $this->entityName;
            }

            protected function defineFields(): FieldCollection
            {
                return new FieldCollection();
            }
        };

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
