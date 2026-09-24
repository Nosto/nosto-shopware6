<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Model\Operation;

use Doctrine\DBAL\Connection;
use Nosto\NostoIntegration\Async\CategorySyncMessage;
use Nosto\NostoIntegration\Async\EntityChangelogSyncMessage;
use Nosto\NostoIntegration\Async\ProductSyncMessage;
use Nosto\NostoIntegration\Entity\Changelog\ChangelogCollection;
use Nosto\NostoIntegration\Entity\Changelog\ChangelogEntity;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Account;
use Nosto\NostoIntegration\Model\Nosto\Account\Provider as AccountProvider;
use Nosto\NostoIntegration\Model\Operation\EntityChangelogSyncHandler;
use Nosto\Scheduler\Model\Job\JobHelper;
use Nosto\Scheduler\Model\Job\JobResult;
use Nosto\Scheduler\Model\JobScheduler;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

final class EntityChangelogSyncHandlerTest extends TestCase
{
    public function testExecuteSchedulesProductAndCategoryJobsAndDeletesProcessedRows(): void
    {
        $context = Context::createDefaultContext();
        $message = new EntityChangelogSyncMessage(Uuid::randomHex(), $context);

        $productId = Uuid::randomHex();
        $categoryId = Uuid::randomHex();
        $productCalls = 0;
        $categoryCalls = 0;

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturnCallback(
            function (Criteria $criteria, Context $context) use (
                &$productCalls,
                &$categoryCalls,
                $productId,
                $categoryId
            ): EntitySearchResult {
                $entityType = self::extractEntityType($criteria);

                if ($entityType === 'product') {
                    ++$productCalls;
                    if ($productCalls > 1) {
                        return self::result([], $criteria, $context);
                    }

                    // Three rows for one product: they must collapse into a single payload entry,
                    // and the newest product number must win.
                    return self::result([
                        self::event($productId, 'product', 'SW-DEMO-OLD'),
                        self::event($productId, 'product', 'SW-DEMO-MID'),
                        self::event($productId, 'product', 'SW-DEMO-1'),
                    ], $criteria, $context);
                }

                if ($entityType === 'category') {
                    ++$categoryCalls;
                    if ($categoryCalls > 1) {
                        return self::result([], $criteria, $context);
                    }

                    return self::result(
                        [self::event($categoryId, 'category', null)],
                        $criteria,
                        $context,
                    );
                }

                return self::result([], $criteria, $context);
            },
        );

        $connection = $this->createMock(Connection::class);

        $deletedParams = [];
        $connection->method('executeStatement')->willReturnCallback(
            static function (string $sql, array $params = [], array $types = []) use (&$deletedParams): int {
                $deletedParams[] = $params;

                return 1;
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

        $accountProvider = $this->createMock(AccountProvider::class);
        $accountProvider->method('all')->willReturn([$account]);

        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('isEnabledMultiCurrency')->willReturn(false);
        $configProvider->method('isEnabledProductSyncExtraLogging')->willReturn(false);

        $handler = new EntityChangelogSyncHandler(
            $repository,
            $connection,
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
        // Three changelog rows, one scheduled product, newest product number kept.
        self::assertSame([
            $productId => 'SW-DEMO-1',
        ], $scheduledMessages[0]->getProductIds());
        self::assertSame([
            'entityType' => 'product',
            'entityIds' => [Uuid::fromHexToBytes($productId)],
        ], $deletedParams[0]);
        self::assertSame([
            'entityType' => 'category',
            'entityIds' => [Uuid::fromHexToBytes($categoryId)],
        ], $deletedParams[1]);
        self::assertSame(2, $jobHelperRecorder->marks[1][1]);
        self::assertTrue($jobHelperRecorder->marks[1][2]);
    }

    public function testExecuteStopsWhenABatchSurvivesItsDeletion(): void
    {
        $context = Context::createDefaultContext();
        $message = new EntityChangelogSyncMessage(Uuid::randomHex(), $context);
        $productId = Uuid::randomHex();

        // The same row comes back every time: the delete never removes anything.
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturnCallback(
            static function (Criteria $criteria, Context $context) use ($productId): EntitySearchResult {
                if (self::extractEntityType($criteria) !== 'product') {
                    return self::result([], $criteria, $context);
                }

                return self::result(
                    [self::event($productId, 'product', 'SW-DEMO-1')],
                    $criteria,
                    $context,
                );
            },
        );

        $connection = $this->createMock(Connection::class);
        // Delete removes nothing, so the same batch would be returned forever.
        $connection->method('executeStatement')->willReturn(0);

        $scheduledMessages = [];
        $jobScheduler = $this->createMock(JobScheduler::class);
        $jobScheduler->method('schedule')->willReturnCallback(
            static function (object $job) use (&$scheduledMessages): void {
                $scheduledMessages[] = $job;
            },
        );

        $account = $this->createMock(Account::class);
        $account->method('getChannelId')->willReturn('channel-id');
        $account->method('getLanguageId')->willReturn('language-id');

        $accountProvider = $this->createMock(AccountProvider::class);
        $accountProvider->method('all')->willReturn([$account]);

        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('isEnabledMultiCurrency')->willReturn(false);
        $configProvider->method('isEnabledProductSyncExtraLogging')->willReturn(false);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $handler = new EntityChangelogSyncHandler(
            $repository,
            $connection,
            $jobScheduler,
            new EntityChangelogRecordingJobHelper(new EntityChangelogJobHelperRecorder()),
            $configProvider,
            $accountProvider,
            $logger,
        );

        $result = $handler->execute($message);

        // The batch is handled once, then the guard aborts instead of looping forever.
        self::assertInstanceOf(JobResult::class, $result);
        self::assertCount(1, $scheduledMessages);
        self::assertInstanceOf(ProductSyncMessage::class, $scheduledMessages[0]);
    }

    private static function extractEntityType(Criteria $criteria): ?string
    {
        foreach ($criteria->getFilters() as $filter) {
            if ($filter instanceof EqualsFilter && $filter->getField() === 'entityType') {
                return (string) $filter->getValue();
            }
        }

        return null;
    }

    private static function event(string $entityId, string $entityType, ?string $productNumber): ChangelogEntity
    {
        $event = new ChangelogEntity();
        $event->setId(Uuid::randomHex());
        $event->setEntityType($entityType);
        $event->setEntityId($entityId);
        $event->setProductNumber($productNumber);

        return $event;
    }

    /**
     * @param list<ChangelogEntity> $events
     */
    private static function result(array $events, Criteria $criteria, Context $context): EntitySearchResult
    {
        return new EntitySearchResult(
            ChangelogEntity::class,
            count($events),
            new ChangelogCollection($events),
            new AggregationResultCollection(),
            $criteria,
            $context,
        );
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
