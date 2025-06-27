<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Controller\Storefront;

use Nosto\NostoIntegration\Service\NostoMonitoringAuthService;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: [
    '_routeScope' => ['storefront'],
])]
class NostoMonitoringLogController extends AbstractNostoMonitoringController
{
    private ParameterBagInterface $parameterBag;

    public function __construct(
        NostoMonitoringAuthService $authService,
        ParameterBagInterface $parameterBag,
    ) {
        parent::__construct($authService);
        $this->parameterBag = $parameterBag;
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
        if ($redirect = $this->requireAuthentication($request)) {
            return $redirect;
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
        if ($redirect = $this->requireAuthentication($request)) {
            return $redirect;
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

        $response = new BinaryFileResponse($zipFile, Response::HTTP_OK, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="logs_archive.zip"',
        ]);

        $response->deleteFileAfterSend();

        return $response;
    }
}
