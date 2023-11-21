<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Api\Route;

use Exception;
use Nosto\NostoIntegration\Async\FullCatalogSyncMessage;
use Nosto\Scheduler\Entity\Job\JobEntity;
use Nosto\Scheduler\Model\JobScheduler;
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
    private JobScheduler $jobScheduler;

    private EntityRepository $jobRepository;

    public function __construct(
        JobScheduler $jobScheduler,
        EntityRepository $jobRepository,
    ) {
        $this->jobScheduler = $jobScheduler;
        $this->jobRepository = $jobRepository;
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
}
