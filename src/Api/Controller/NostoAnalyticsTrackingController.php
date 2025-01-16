<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Api\Controller;

use Nosto\Operation\Search\AnalyticsSearchTracking;
use Nosto\Operation\Category\AnalyticsCategoryTracking;
use Nosto\SDK\NostoClient;
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
    private NostoClient $nostoClient;

    public function __construct(NostoClient $nostoClient)
    {
        $this->nostoClient = $nostoClient;
    }

    #[Route(
        path: "/nosto/analytics-tracking",
        name: "storefront.nosto.analytics-tracking",
        methods: ["POST"],
    )]
    public function trackAnalytics(Request $request, Context $context): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['dataSource']) || !isset($data['productNumber'])) {
            return new JsonResponse(['error' => 'Missing dataSource or productNumber'], Response::HTTP_BAD_REQUEST);
        }

        $dataSource = $data['dataSource'];
        $payload = [
            'query' => $data['productNumber'],
            'resultId' => $data['resultId'] ?? 'DEMODEMODEMO',
            'isOrganic' => $data['isOrganic'] ?? false,
            'isAutoCorrect' => $data['isAutoCorrect'] ?? true,
            'isAutoComplete' => $data['isAutoComplete'] ?? false,
            'isKeyword' => $data['isKeyword'] ?? false,
            'isSorted' => $data['isSorted'] ?? true,
            'hasResults' => $data['hasResults'] ?? true,
            'isRefined' => $data['isRefined'] ?? false,
        ];

        try {
            if ($dataSource === 'search') {
                $tracker = new AnalyticsSearchTracking($this->nostoClient);
            } elseif ($dataSource === 'category') {
                $tracker = new AnalyticsCategoryTracking($this->nostoClient);
            } else {
                return new JsonResponse(['error' => 'Invalid dataSource'], Response::HTTP_BAD_REQUEST);
            }

            $success = $tracker->track($dataSource, $payload);

            if ($success) {
                return new JsonResponse(['status' => 'success']);
            }

            return new JsonResponse(['error' => 'Failed to track analytics'], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Error sending data to Nosto',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
