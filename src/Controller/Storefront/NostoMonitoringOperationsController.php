<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Controller\Storefront;

use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\NostoMonitoringHelper;
use Nosto\NostoIntegration\Service\NostoMonitoringAuthService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: [
    '_routeScope' => ['storefront'],
])]
class NostoMonitoringOperationsController extends AbstractNostoMonitoringController
{
    public function __construct(
        NostoMonitoringAuthService $authService,
        private readonly NostoMonitoringHelper $nostoMonitoringHelper,
    ) {
        parent::__construct($authService);
    }

    #[Route(
        path: "/nosto-monitoring/manage-operations",
        name: "nosto-monitoring.manage-operations",
        options: [
            "seo" => "false",
        ],
        methods: ["GET"],
    )]
    public function nostoMangeOperations(Request $request): Response
    {
        if ($redirect = $this->requireAuthentication($request)) {
            return $redirect;
        }

        return $this->renderStorefront(
            '@NostoMonitoringController/storefront/page/nosto-monitoring/manage-operations.html.twig',
            [
                'nostoConfig' => $this->nostoMonitoringHelper->getSerializedNostoConfiguration(
                    $request->get('salesChannelId'),
                    $request->get('languageId'),
                ),
            ],
        );
    }

    #[Route(
        path: "/nosto-monitoring/clear-jobs",
        name: "nosto-monitoring.clear-jobs",
        options: [
            "seo" => "false",
        ],
        methods: ["POST"],
    )]
    public function clearNostoJobsFromDB(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireAuthentication($request)) {
            return $redirect;
        }

        $clearedJobs = $this->nostoMonitoringHelper->clearNostoJobs();
        $request->getSession()->getFlashBag()->add($clearedJobs['status'], $clearedJobs['message']);

        return $this->redirectToRoute('nosto-monitoring.manage-operations');
    }
}
