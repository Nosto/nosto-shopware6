<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Api\Route;

use Exception;
use Nosto\NostoIntegration\Async\FullCatalogSyncMessage;
use Nosto\NostoIntegration\Service\NostoJobSyncService;
use Nosto\Scheduler\Entity\Job\JobEntity;
use Nosto\Scheduler\Model\JobScheduler;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\Response\JsonApiResponse;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(
    defaults: [
        '_routeScope' => ['api'],
    ],
)]
class NostoSyncRoute
{
    public function __construct(
        private readonly JobScheduler $jobScheduler,
        private readonly EntityRepository $jobRepository,
        private readonly NostoJobSyncService $jobSyncService,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route(
        path: "/api/schedule-full-product-sync",
        name: "api.nosto_integration_sync.full_sync",
        methods: ["POST"],
    )]
    public function fullCatalogSync(Request $request, Context $context): JsonApiResponse
    {
        $job = new FullCatalogSyncMessage(Uuid::randomHex(), $context);
        $this->checkJobStatus($context, $job->getHandlerCode());
        $this->jobScheduler->schedule($job);
        return new JsonApiResponse();
    }

    #[Route(
        path: "/api/delete-running-full-product-sync-job",
        name: "api.nosto_integration_sync.delete_running_full_product_sync_job",
        methods: ["POST"],
    )]
    public function deleteRunningFullProductSyncJob(Request $request, Context $context): JsonApiResponse
    {
        try {
            $job = new FullCatalogSyncMessage(Uuid::randomHex(), $context);
            $this->checkCancelRunningFullProductSyncJobStatus($context, $job->getHandlerCode());
            $this->jobSyncService->deleteRunningFullProductSyncJob($context, $job->getHandlerCode());
            return new JsonApiResponse();
        } catch (Exception $e) {
            $this->logger->error('Unable to delete running full product sync job due to:' . $e->getMessage());
            throw new Exception($e->getMessage());
        }
    }

    private function checkJobStatus(Context $context, string $type): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new AndFilter([
                new EqualsFilter('type', $type),
                new EqualsAnyFilter('status', [JobEntity::TYPE_PENDING, JobEntity::TYPE_RUNNING]),
            ]),
        );
        /** @var JobEntity $job */
        if ($job = $this->jobRepository->search($criteria, $context)->first()) {
            $message = $job->getStatus() === JobEntity::TYPE_PENDING
                ? 'Job is already scheduled.'
                : 'Job is already running.';

            throw new Exception($message);
        }
    }

    private function checkCancelRunningFullProductSyncJobStatus(Context $context, string $type): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new AndFilter([
                new EqualsFilter('type', $type),
                new EqualsAnyFilter('status', [JobEntity::TYPE_PENDING, JobEntity::TYPE_RUNNING]),
            ]),
        );
        /** @var JobEntity $job */
        if (!$this->jobRepository->search($criteria, $context)->first()) {
            $message = 'There are currently no jobs available for cancellation.';

            throw new Exception($message);
        }
    }
}
