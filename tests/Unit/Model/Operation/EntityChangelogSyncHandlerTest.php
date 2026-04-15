<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Model\Operation;

use Nosto\NostoIntegration\Async\CategorySyncMessage;
use Nosto\NostoIntegration\Async\EntityChangelogSyncMessage;
use Nosto\NostoIntegration\Async\ProductSyncMessage;
use Nosto\NostoIntegration\Entity\Changelog\ChangelogCollection;
use Nosto\NostoIntegration\Entity\Changelog\ChangelogEntity;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Account;
use Nosto\NostoIntegration\Model\Operation\EntityChangelogSyncHandler;
use Nosto\Scheduler\Model\Job\JobHelper;
use Nosto\Scheduler\Model\Job\JobResult;
use Nosto\Scheduler\Model\JobScheduler;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;

final class EntityChangelogSyncHandlerTest extends TestCase
{
    public function testExecuteSchedulesProductAndCategoryJobsAndDeletesProcessedRows(): void
    {
        $context = Context::createDefaultContext();
        $message = new EntityChangelogSyncMessage(Uuid::randomHex(), $context);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('getDefinition')->willReturn($this->createDefinition('changelog_test'));

        $productCalls = 0;
        $categoryCalls = 0;
        $repository->method('search')->willReturnCallback(
            static function (Criteria $criteria, Context $context) use (
                &$productCalls,
                &$categoryCalls
            ): EntitySearchResult {
                $entityType = self::extractEntityType($criteria);

                if ($entityType === 'product') {
                    ++$productCalls;
                    if ($productCalls > 1) {
                        return self::emptyResult(ProductDefinition::ENTITY_NAME, $criteria, $context);
                    }

                    return self::productResult($criteria, $context, 'product-change-1', 'SW-DEMO-1');
                }

                if ($entityType === 'category') {
                    ++$categoryCalls;
                    if ($categoryCalls > 1) {
                        return self::emptyResult('category', $criteria, $context);
                    }

                    return self::categoryResult($criteria, $context, 'category-change-1');
                }

                return self::emptyResult($entityType ?? 'unknown', $criteria, $context);
            },
        );

        $deletedRows = [];
        $deleteEvent = $this->createMock(EntityWrittenContainerEvent::class);
        $repository->method('delete')->willReturnCallback(
            static function (array $rows) use (&$deletedRows, $deleteEvent): EntityWrittenContainerEvent {
                $deletedRows[] = $rows;

                return $deleteEvent;
            },
        );

        $scheduledMessages = [];
        $jobScheduler = $this->createMock(JobScheduler::class);
        $jobScheduler->method('schedule')->willReturnCallback(
            static function (object $job) use (&$scheduledMessages): void {
                $scheduledMessages[] = $job;
            },
        );

        $jobHelperRecorder = new EntityChangelogJobHelperRecorder();
        $jobHelper = new EntityChangelogRecordingJobHelper($jobHelperRecorder);

        $account = $this->createMock(Account::class);
        $account->method('getChannelId')->willReturn('channel-id');
        $account->method('getLanguageId')->willReturn('language-id');

        $accountProvider = $this->createMock(\Nosto\NostoIntegration\Model\Nosto\Account\Provider::class);
        $accountProvider->method('all')->willReturn([$account]);

        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('isEnabledMultiCurrency')->willReturn(false);
        $configProvider->method('isEnabledProductSyncExtraLogging')->willReturn(false);

        $handler = new EntityChangelogSyncHandler(
            $repository,
            $jobScheduler,
            $jobHelper,
            $configProvider,
            $accountProvider,
            $this->createMock(LoggerInterface::class),
        );

        $result = $handler->execute($message);

        self::assertInstanceOf(JobResult::class, $result);
        self::assertCount(2, $scheduledMessages);
        self::assertInstanceOf(ProductSyncMessage::class, $scheduledMessages[0]);
        self::assertInstanceOf(CategorySyncMessage::class, $scheduledMessages[1]);
        self::assertSame([
            'product-id-1' => 'SW-DEMO-1',
        ], $scheduledMessages[0]->getProductIds());
        self::assertSame([[
            'id' => 'product-change-1',
        ]], $deletedRows[0]);
        self::assertSame([[
            'id' => 'category-change-1',
        ]], $deletedRows[1]);
        self::assertSame(2, $jobHelperRecorder->marks[1][1]);
        self::assertTrue($jobHelperRecorder->marks[1][2]);
    }

    private static function extractEntityType(Criteria $criteria): ?string
    {
        foreach ($criteria->getFilters() as $filter) {
            if ($filter instanceof \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter && $filter->getField() === 'entityType') {
                return (string) $filter->getValue();
            }
        }

        return null;
    }

    private static function productResult(
        Criteria $criteria,
        Context $context,
        string $id,
        string $productNumber,
    ): EntitySearchResult {
        $entity = new ChangelogEntity();
        $entity->setId($id);
        $entity->setEntityType(ProductDefinition::ENTITY_NAME);
        $entity->setEntityId('product-id-1');
        $entity->setProductNumber($productNumber);

        return new EntitySearchResult(
            ChangelogEntity::class,
            1,
            new ChangelogCollection([$entity]),
            new AggregationResultCollection(),
            $criteria,
            $context,
        );
    }

    private static function categoryResult(Criteria $criteria, Context $context, string $id): EntitySearchResult
    {
        $entity = new ChangelogEntity();
        $entity->setId($id);
        $entity->setEntityType('category');
        $entity->setEntityId('category-id-1');
        $entity->setProductNumber(null);

        return new EntitySearchResult(
            ChangelogEntity::class,
            1,
            new ChangelogCollection([$entity]),
            new AggregationResultCollection(),
            $criteria,
            $context,
        );
    }

    private static function emptyResult(string $entity, Criteria $criteria, Context $context): EntitySearchResult
    {
        return new EntitySearchResult(
            ChangelogEntity::class,
            0,
            new ChangelogCollection(),
            new AggregationResultCollection(),
            $criteria,
            $context,
        );
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

final class EntityChangelogJobHelperRecorder
{
    /**
     * @var array<int, array{0: string, 1: int, 2: bool}>
     */
    public array $marks = [];
}

readonly class EntityChangelogRecordingJobHelper extends JobHelper
{
    public function __construct(
        private EntityChangelogJobHelperRecorder $recorder,
    ) {
    }

    public function markChildGenerationState(string $jobId, int $count, bool $done): void
    {
        $this->recorder->marks[] = [$jobId, $count, $done];
    }
}
