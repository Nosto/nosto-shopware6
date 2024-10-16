<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Controller\Storefront;

use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\NostoMonitoringHelper;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(
    defaults: [
        '_routeScope' => ['storefront'],
    ],
)]
class NostoMonitoringController extends StorefrontController
{
    const SESSION_KEY = 'nostoAccessKey';

    const DEMO_KEY = 'Soprex';

    public function __construct(
        private readonly NostoMonitoringHelper $nostoMonitoringHelper,
    ) {
    }

    #[Route(
        path: "/nosto-monitoring",
        name: "nosto-monitoring.access-page",
        options: [
            "seo" => "false",
        ],
        methods: ["GET"],
    )]
    public function index(): Response
    {
        return $this->renderStorefront('@NostoMonitoringController/storefront/page/nosto-monitoring/monitoring-access.html.twig', []);
    }

    #[Route(
        path: "/nosto-monitoring/validate-access-key",
        name: "nosto-monitoring.access-key.validation",
        options: [
            "seo" => "false",
        ],
        methods: ["POST"],
    )]
    public function validateAccessKey(Request $request)
    {
        $accessKey['accessKey'] = $request->get(self::SESSION_KEY);

        if ($accessKey['accessKey'] !== self::DEMO_KEY) {
            return $this->redirectToRoute('nosto-monitoring.access-page');
        }

        $request->getSession()->set(self::SESSION_KEY, $accessKey['accessKey']);

        return $this->redirectToRoute('nosto-monitoring.manage-operations');
    }

    #[Route(
        path: "/nosto-monitoring/manage-operations",
        name: "nosto-monitoring.manage-operations",
        options: [
            "seo" => "false",
        ],
        methods: ["GET"],
    )]
    public function nostoMangeOperations(Request $request)
    {
        $accessKey = $request->getSession()->get('nostoAccessKey');
        if (!$accessKey) {
            return $this->redirectToRoute('nosto-monitoring.access-page');
        }

        return $this->renderStorefront('@NostoMonitoringController/storefront/page/nosto-monitoring/manage-operations.html.twig');
    }

    #[Route(
        path: "/nosto-monitoring/clear-jobs",
        name: "nosto-monitoring.clear-jobs",
        options: [
            "seo" => "false",
        ],
        methods: ["POST"],
    )]
    public function clearNostoJobsFromDB(Request $request)
    {
        $accessKey = $request->getSession()->get('nostoAccessKey');

        if (!$accessKey) {
            return $this->redirectToRoute('nosto-monitoring.access-page');
        }

        $clearedJobs = $this->nostoMonitoringHelper->clearNostoJobs();
        $request->getSession()->getFlashBag()->add($clearedJobs['status'], $clearedJobs['message']);

        return $this->redirectToRoute('nosto-monitoring.manage-operations');
    }
}
