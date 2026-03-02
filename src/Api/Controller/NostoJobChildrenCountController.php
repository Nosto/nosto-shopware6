<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Api\Controller;

use Nosto\NostoIntegration\Service\NostoJobChildrenCountService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(
    defaults: [
        '_routeScope' => ['api'],
    ],
)]
class NostoJobChildrenCountController extends AbstractController
{
    public function __construct(
        private readonly NostoJobChildrenCountService $jobChildrenCountService,
    ) {
    }

    #[Route(
        path: '/api/_action/nosto-integration/job-child-count',
        name: 'api.action.nosto_integration.job_child_count',
        methods: ['POST'],
    )]
    public function childCountAction(Request $request): JsonResponse
    {
        $parentJobIds = $request->request->all('parentJobIds');

        if (!is_array($parentJobIds) || $parentJobIds === []) {
            return new JsonResponse(['data' => []]);
        }

        $data = $this->jobChildrenCountService->getChildCount($parentJobIds);

        return new JsonResponse(['data' => $data]);
    }
}
