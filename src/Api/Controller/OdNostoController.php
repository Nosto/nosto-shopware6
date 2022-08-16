<?php

declare(strict_types=1);

namespace Od\NostoIntegration\Api\Controller;

use Od\NostoIntegration\Async\FullCatalogSyncMessage;
use Od\Scheduler\Entity\Job\JobEntity;
use Od\Scheduler\Model\JobScheduler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class OdNostoController extends AbstractController
{
    private JobScheduler $jobScheduler;
    private EntityRepositoryInterface $jobRepository;

    public function __construct(
        JobScheduler $jobScheduler,
        EntityRepositoryInterface $jobRepository
    ) {
        $this->jobScheduler = $jobScheduler;
        $this->jobRepository = $jobRepository;
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/_action/od-nosto/schedule-full-product-sync",
     *     name="api.action.od-nosto.schedule.full.product.sync",
     *     methods={"POST"}
     * )
     * @param Request $request
     * @return JsonResponse
     */
    public function fullCatalogSyncAction(Request $request, Context $context): JsonResponse
    {
        $job = new FullCatalogSyncMessage(Uuid::randomHex());
        $this->checkJobStatus($context, $job->getHandlerCode());
        $this->jobScheduler->schedule($job);

        return new JsonResponse();
    }

    private function checkJobStatus(Context $context, string $type)
    {
        $criteria = new Criteria();
        $criteria->addFilter(new AndFilter([
            new EqualsFilter('type', $type),
            new EqualsAnyFilter('status', [JobEntity::TYPE_PENDING, JobEntity::TYPE_RUNNING])
        ]));
        /** @var JobEntity $job */
        if ($job = $this->jobRepository->search($criteria, $context)->first()) {
            $message = $job->getStatus() === JobEntity::TYPE_PENDING
                ? 'Job is already scheduled.'
                : 'Job is already running.';

            throw new \Exception($message);
        }
    }
}
