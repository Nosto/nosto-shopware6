<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Api\Controller;

use Doctrine\DBAL\Connection;
use Nosto\NostoIntegration\Model\Config\NostoConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;

#[Route(
    defaults: [
        '_routeScope' => ['api'],
    ],
)]
class NostoConfigController extends AbstractController
{
    public function __construct(
        private readonly NostoConfigService $nostoConfigService,
        private readonly Connection $connection,
    ) {
    }

    #[Route(path: '/api/_action/nosto-config', name: 'api.action.nosto_integration.config', methods: ['GET'])]
    public function getConfigurationValues(Request $request): JsonResponse
    {
        $languageId = $request->query->get('languageId');
        $salesChannelId = $request->query->get('salesChannelId');

        $config = $this->nostoConfigService->getConfig($salesChannelId, $languageId);

        if (empty($config)) {
            $json = '{}';
        } else {
            $json = json_encode($config, JSON_PRESERVE_ZERO_FRACTION);
        }

        return new JsonResponse($json, 200, [], true);
    }

    #[Route(
        path: '/api/_action/nosto-config',
        name: 'api.action.nosto_integration.config.save',
        methods: ['POST'],
    )]
    public function saveConfiguration(Request $request): Response
    {
        $salesChannelId = $request->query->get('salesChannelId');
        $languageId = $request->query->get('languageId');
        $config = $request->request->all();
        $this->saveKeyValues($salesChannelId, $languageId, $config);

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    #[Route(
        path: '/api/_action/nosto-config/batch',
        name: 'api.action.nosto_integration.config.save.batch',
        methods: ['POST'],
    )]
    public function batchSaveConfiguration(Request $request): Response
    {
        $this->connection->beginTransaction();
        try {
            foreach ($request->request->all() as $key => $config) {
                [$salesChannelId, $languageId] = $key === 'null'
                    ? [null, null]
                    : explode('-', $key);

                $this->saveKeyValues($salesChannelId, $languageId, $config);
            }
        } catch (Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }

        $this->connection->commit();

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    private function saveKeyValues(?string $salesChannelId, ?string $languageId, array $config): void
    {
        foreach ($config as $key => $value) {
            $this->nostoConfigService->set($key, $value, $salesChannelId, $languageId);
        }
    }
}
