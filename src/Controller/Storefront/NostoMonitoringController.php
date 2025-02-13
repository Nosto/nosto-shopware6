<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Controller\Storefront;

use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\NostoMonitoringHelper;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
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
    public const SESSION_KEY = 'nostoAccessKey';

    public const DEMO_KEY = 'Soprex';

    private ParameterBagInterface $parameterBag;

    public function __construct(
        private readonly NostoMonitoringHelper $nostoMonitoringHelper,
        ParameterBagInterface $parameterBag,
    ) {
        $this->parameterBag = $parameterBag;
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
        return $this->renderStorefront(
            '@NostoMonitoringController/storefront/page/nosto-monitoring/monitoring-access.html.twig',
            [],
        );
    }

    #[Route(
        path: "/nosto-monitoring/validate-access-key",
        name: "nosto-monitoring.access-key.validation",
        options: [
            "seo" => "false",
        ],
        methods: ["POST"],
    )]
    public function validateAccessKey(Request $request): RedirectResponse
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
    public function nostoMangeOperations(Request $request): Response
    {
        $accessKey = $request->getSession()->get('nostoAccessKey');
        if (!$accessKey) {
            return $this->redirectToRoute('nosto-monitoring.access-page');
        }

        return $this->renderStorefront(
            '@NostoMonitoringController/storefront/page/nosto-monitoring/manage-operations.html.twig',
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
        $accessKey = $request->getSession()->get('nostoAccessKey');

        if (!$accessKey) {
            return $this->redirectToRoute('nosto-monitoring.access-page');
        }

        $clearedJobs = $this->nostoMonitoringHelper->clearNostoJobs();
        $request->getSession()->getFlashBag()->add($clearedJobs['status'], $clearedJobs['message']);

        return $this->redirectToRoute('nosto-monitoring.manage-operations');
    }

    #[Route(
        path: "/nosto-monitoring/logs",
        name: "nosto-monitoring.logs",
        options: [
            "seo" => "false",
        ],
        methods: ["GET"],
    )]
    public function getLogs(Request $request): JsonResponse|RedirectResponse
    {
        $accessKey = $request->getSession()->get('nostoAccessKey');

        if (!$accessKey) {
            return $this->redirectToRoute('nosto-monitoring.access-page');
        }

        $logDir = $this->parameterBag->get('kernel.logs_dir');

        if (!is_dir($logDir)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Log directory not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $logFiles = array_diff(scandir($logDir), ['.', '..']);
        $logs = [];

        foreach ($logFiles as $file) {
            $logs[] = [
                'name' => $file,
                'path' => $logDir . DIRECTORY_SEPARATOR . $file,
            ];
        }

        return new JsonResponse([
            'success' => true,
            'logs' => $logs,
        ]);
    }

    #[Route(
        path: "/nosto-monitoring/log-download-all",
        name: "nosto-monitoring.log-download-all",
        options: [
            "seo" => "false",
        ],
        methods: ["GET"],
    )]
    public function downloadAllLogs(Request $request): JsonResponse|RedirectResponse|BinaryFileResponse
    {
        $accessKey = $request->getSession()->get('nostoAccessKey');

        if (!$accessKey) {
            return $this->redirectToRoute('nosto-monitoring.access-page');
        }

        $logDir = $this->parameterBag->get('kernel.logs_dir');
        $zipFile = $logDir . DIRECTORY_SEPARATOR . 'logs_archive.zip';

        $zip = new \ZipArchive();
        if ($zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Failed to create ZIP archive.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $files = glob($logDir . DIRECTORY_SEPARATOR . '*.log');
        if (!$files) {
            return new JsonResponse([
                'success' => false,
                'message' => 'No log files found.',
            ], Response::HTTP_NOT_FOUND);
        }

        foreach ($files as $file) {
            $zip->addFile($file, basename($file));
        }
        $zip->close();

        return new BinaryFileResponse($zipFile, Response::HTTP_OK, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="logs_archive.zip"',
        ]);
    }
}
