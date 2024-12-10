<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Api\Controller;

use GuzzleHttp\Client;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Shopware\Core\Framework\Context;

#[Route(
    defaults: [
        '_routeScope' => ['storefront'],
    ],
)]
class TrackProductClickController extends AbstractController
{
    protected const APP_URL = 'https://api.nosto.com/v1/graphql';

    private readonly Client $client;

    public function __construct(
        private readonly ConfigProvider $configProvider
    ) {
        $this->client = new Client();
    }

    #[Route(
        path: "/nosto/track-product-click",
        name: "storefront.nosto.track_product_click",
        methods: ["POST"],
    )]
    public function trackProductClick(Request $request, Context $context): JsonResponse
    {

        dd($context);
        $data = json_decode($request->getContent(), true);

        if (!isset($data['dataSource']) || !isset($data['productNumber'])) {
            return new JsonResponse(['error' => 'Missing dataSource or productNumber'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $dataSource = $data['dataSource'];
        $productNumber = $data['productNumber'];

//        $appToken = $this->configProvider->getAppToken(
//            $request->request->get('salesChannelId'),
//            $request->request->get('languageId')
//        );
//
//
//        if (!$appToken) {
//            return new JsonResponse(['error' => 'App token missing'], JsonResponse::HTTP_NOT_FOUND);
//        }

        $mutation = <<<EOF
    mutation TrackProductEvent(\$input: ProductEventInput!) {
        trackProductEvent(input: \$input) {
            success
            message
        }
    }
    EOF;
        $variables = [
            "input" => [
                "dataSource" => $dataSource,
                "productNumber" => $productNumber,
                "metadata" => [
                    "timestamp" => date('c'), // ISO 8601 format
                ],
                "properties" => [
                    'abTestAttribution' => [
                        'testId1' => 'A',
                        'testId2' => 'B',
                    ],
                ],
            ],
        ];

        // Prepare the payload
        $payload = json_encode([
            "query" => $mutation,
            "variables" => $variables,
        ]);

        $response = $this->client->post(self::APP_URL, [
            'headers' => [
                'Content-Type' => 'application/json',

            ],
            'body' => $payload,
        ]);

        $responseData = json_decode($response->getBody()->getContents(), true);
        echo '<pre>';
        print_r($responseData);
        die;

        try {
            $this->client->post(self::APP_URL, [
                'headers' => [
                    'Content-Type' => 'application/graphql',
                ],
                'auth' => ['', $appToken],
                'body' => $mutation,
            ]);

            return new JsonResponse(['status' => 'success']);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Error sending data to Nosto', 'message' => $e->getMessage()], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
