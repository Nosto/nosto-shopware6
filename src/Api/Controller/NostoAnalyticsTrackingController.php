<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Api\Controller;

use Nosto\Model\Analytics\AnalyticsTrackingPayload;
use Nosto\Model\Analytics\DataSource;
use Nosto\Operation\Category\AnalyticsCategoryTracking;
use Nosto\Operation\Search\AnalyticsSearchTracking;
use Nosto\Request\Http\Adapter\Curl;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(
    defaults: [
        '_routeScope' => ['storefront'],
    ],
)]
class NostoAnalyticsTrackingController extends AbstractController
{
    private Curl $curl;

    public function __construct()
    {
        $this->curl = new Curl('');
    }

    #[Route(
        path: "/nosto/analytics-tracking",
        name: "storefront.nosto.analytics-tracking",
        methods: ["POST"],
    )]
    public function trackAnalytics(Request $request, Context $context): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['dataSource'])) {
            return new JsonResponse([
                'error' => 'Missing dataSource',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $dataSource = DataSource::fromString($data['dataSource']);

            $payload = new AnalyticsTrackingPayload(
                $data['query'] ?? null,
                $data['productNumber'] ?? null,
                $data['resultId'] ?? uniqid(),
                $data['isOrganic'] ?? false,
                $data['isAutoCorrect'] ?? true,
                $data['isAutoComplete'] ?? false,
                $data['isKeyword'] ?? false,
                $data['isSorted'] ?? true,
                $data['hasResults'] ?? true,
                $data['isRefined'] ?? false,
            );

            $tracker = match ($dataSource->getType()) {
                DataSource::SEARCH => new AnalyticsSearchTracking($this->curl),
                DataSource::CATEGORY => new AnalyticsCategoryTracking($this->curl),
                default => throw new \InvalidArgumentException('Invalid dataSource'),
            };

            $tracker->track($dataSource, $payload);

            return new JsonResponse([
                'status' => 'success',
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Error sending data to Nosto',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
