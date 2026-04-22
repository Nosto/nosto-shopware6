<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Api\Controller;

use Nosto\NostoIntegration\Service\Mcp\NostoDocumentationMcpProxy;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(
    defaults: [
        '_routeScope' => ['api'],
    ],
)]
final class NostoMcpController extends AbstractController
{
    public function __construct(
        private readonly NostoDocumentationMcpProxy $proxy,
    ) {
    }

    #[Route(
        path: '/api/_action/nosto-docs-mcp/documentation',
        name: 'api.action.nosto.docs.mcp.documentation',
        options: [
            'auth_required' => 'false',
        ],
        methods: ['POST'],
    )]
    public function documentation(Request $request): JsonResponse
    {
        try {
            $payload = $request->toArray();
        } catch (JsonException) {
            return new JsonResponse([
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'The request body must be valid JSON.',
                    ],
                ],
                'isError' => true,
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        return new JsonResponse($this->proxy->forwardDocumentationRequest($payload));
    }
}
