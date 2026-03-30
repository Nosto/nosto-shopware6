<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Api\Route;

use Exception;
use Nosto\NostoIntegration\Api\Route\NostoSyncRoute;
use Nosto\NostoIntegration\Async\FullCatalogSyncMessage;
use Nosto\NostoIntegration\Service\NostoJobSyncService;
use Nosto\Scheduler\Entity\Job\JobEntity;
use Nosto\Scheduler\Model\JobScheduler;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\Response\JsonApiResponse;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;

final class NostoSyncRouteTest extends TestCase
{
    public function testFullCatalogSyncSchedulesTheJobWhenNoOtherJobIsRunning(): void
    {
        $jobScheduler = $this->createMock(JobScheduler::class);
        $jobRepository = $this->createMock(EntityRepository::class);
        $jobSyncService = $this->createMock(NostoJobSyncService::class);
        $logger = $this->createMock(LoggerInterface::class);
        $searchResult = $this->createSearchResult([]);

        $jobRepository->expects($this->once())
            ->method('search')
            ->willReturn($searchResult);
        $jobScheduler->expects($this->once())
            ->method('schedule')
            ->with($this->callback(static fn (object $message): bool => $message instanceof FullCatalogSyncMessage));

        $route = new NostoSyncRoute($jobScheduler, $jobRepository, $jobSyncService, $logger);

        $response = $route->fullCatalogSync(new Request(), Context::createDefaultContext());

        self::assertInstanceOf(JsonApiResponse::class, $response);
    }

    public function testFullCatalogSyncRejectsWhenTheJobIsAlreadyScheduled(): void
    {
        $jobScheduler = $this->createMock(JobScheduler::class);
        $jobRepository = $this->createMock(EntityRepository::class);
        $jobSyncService = $this->createMock(NostoJobSyncService::class);
        $logger = $this->createMock(LoggerInterface::class);
        $job = new JobEntity();
        $job->setId(Uuid::randomHex());
        $job->setStatus(JobEntity::TYPE_PENDING);
        $searchResult = $this->createSearchResult([$job]);

        $jobRepository->expects($this->once())
            ->method('search')
            ->willReturn($searchResult);
        $jobScheduler->expects($this->never())
            ->method('schedule');

        $route = new NostoSyncRoute($jobScheduler, $jobRepository, $jobSyncService, $logger);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Job is already scheduled.');

        $route->fullCatalogSync(new Request(), Context::createDefaultContext());
    }

    public function testFullCatalogSyncRejectsWhenTheJobIsAlreadyRunning(): void
    {
        $jobScheduler = $this->createMock(JobScheduler::class);
        $jobRepository = $this->createMock(EntityRepository::class);
        $jobSyncService = $this->createMock(NostoJobSyncService::class);
        $logger = $this->createMock(LoggerInterface::class);
        $job = new JobEntity();
        $job->setId(Uuid::randomHex());
        $job->setStatus(JobEntity::TYPE_RUNNING);
        $searchResult = $this->createSearchResult([$job]);

        $jobRepository->expects($this->once())
            ->method('search')
            ->willReturn($searchResult);
        $jobScheduler->expects($this->never())
            ->method('schedule');

        $route = new NostoSyncRoute($jobScheduler, $jobRepository, $jobSyncService, $logger);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Job is already running.');

        $route->fullCatalogSync(new Request(), Context::createDefaultContext());
    }

    public function testDeleteRunningFullProductSyncJobRejectsWhenNoJobCanBeCancelled(): void
    {
        $jobScheduler = $this->createMock(JobScheduler::class);
        $jobRepository = $this->createMock(EntityRepository::class);
        $jobSyncService = $this->createMock(NostoJobSyncService::class);
        $logger = $this->createMock(LoggerInterface::class);
        $searchResult = $this->createSearchResult([]);

        $jobRepository->expects($this->once())
            ->method('search')
            ->willReturn($searchResult);
        $jobSyncService->expects($this->never())
            ->method('deleteRunningFullProductSyncJob');
        $logger->expects($this->once())
            ->method('error')
            ->with($this->stringStartsWith('Unable to delete running full product sync job due to:'));

        $route = new NostoSyncRoute($jobScheduler, $jobRepository, $jobSyncService, $logger);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('There are currently no jobs available for cancellation.');

        $route->deleteRunningFullProductSyncJob(new Request(), Context::createDefaultContext());
    }

    /**
     * @param array<JobEntity> $entities
     */
    private function createSearchResult(array $entities): EntitySearchResult
    {
        $collection = new EntityCollection();

        foreach ($entities as $entity) {
            $collection->add($entity);
        }

        return new EntitySearchResult(
            JobEntity::class,
            $collection->count(),
            $collection,
            new AggregationResultCollection(),
            new Criteria(),
            Context::createDefaultContext(),
        );
    }
}
