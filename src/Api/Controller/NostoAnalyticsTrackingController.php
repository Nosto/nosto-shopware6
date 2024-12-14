<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Api\Controller;

use GuzzleHttp\Client;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Shopware\Core\Framework\Context;

#[Route(
    defaults: [
        '_routeScope' => ['storefront'],
    ],
)]
class NostoAnalyticsTrackingController extends AbstractController
{
    /**
     * Nosto Connect API
     */
    protected const APP_URL = 'https://connect.nosto.com/analytics';

    private readonly Client $client;

    public function __construct(
    ) {
        $this->client = new Client();
    }

    #[Route(
        path: "/nosto/analytics-tracking",
        name: "storefront.nosto.analytics-tracking",
        methods: ["POST"],
    )]
    public function trackProductClick(Request $request, Context $context): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['dataSource']) || !isset($data['productNumber'])) {
            return new JsonResponse(['error' => 'Missing dataSource or productNumber'], Response::HTTP_BAD_REQUEST);
        }

        $dataSource = $data['dataSource'];
        $productNumber = $data['productNumber'];
        $productId = $data['productId'];
        $apiUrl = self::APP_URL . '/' . $dataSource . '/click';

        // TODO: Check resultId
        $payload = [
            'metadata' => [
                'query' => $productNumber,
                'resultId' => 'DEMODEMODEMO',
                'isOrganic' => false,
                'isAutoCorrect' => true,
                'isAutoComplete' => false,
                'isKeyword' => false,
                'isSorted' => true,
                'hasResults' => true,
                'isRefined' => false,
            ],
            'productId' => $productId,
            'properties' => [],
        ];

        // TODO: DEMO
        try {
            $response = $this->client->post($apiUrl, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'query' => [
                    'merchant' => 'pe5iajdk',
                    'c' => 'nostouz.soprexdev.com',
                ],
                'body' => json_encode($payload),
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode !== Response::HTTP_OK) {
                return new JsonResponse(['status' => $statusCode]);
            }

            return new JsonResponse(['status' => 'success']);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Error sending data to Nosto', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
