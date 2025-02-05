<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Api\Controller;

use Nosto\Model\Analytics\AnalyticsTrackingPayload;
use Nosto\Model\Analytics\DataSource;
use Nosto\NostoIntegration\Model\Nosto\Account;
use Nosto\Operation\Category\AnalyticsCategoryTracking;
use Nosto\Operation\Search\AnalyticsSearchTracking;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
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
    public function __construct(
        private readonly Account\Provider $accountProvider
    ) {
    }

    #[Route(
        path: "/nosto/analytics-tracking",
        name: "storefront.nosto.analytics-tracking",
        methods: ["POST"],
    )]
    public function trackAnalytics(Request $request, SalesChannelContext $context): JsonResponse
    {
        $channelId = $context->getSalesChannelId();
        $languageId = $context->getLanguageId();

        $nostoAccount = $this->accountProvider->get($context->getContext(), $channelId, $languageId);

        $data = json_decode($request->getContent(), true);

        if (!isset($data['dataSource'])) {
            return new JsonResponse([
                'error' => 'Missing dataSource',
            ], Response::HTTP_BAD_REQUEST);
        }

        if ((!isset($data['trackingType']) || $data['trackingType'] == 'search') && !isset($data['query'])) {
            return new JsonResponse([
                'error' => 'Missing query',
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
                DataSource::SEARCH => new AnalyticsSearchTracking($nostoAccount->getNostoAccount()),
                DataSource::CATEGORY => new AnalyticsCategoryTracking($nostoAccount->getNostoAccount()),
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
