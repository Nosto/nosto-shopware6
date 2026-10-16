<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Api\Controller;

use Nosto\Model\Analytics\AnalyticsCategoryMetadata;
use Nosto\Model\Analytics\AnalyticsSearchMetadataForGraphql;
use Nosto\Model\Analytics\DataSource;
use Nosto\NostoIntegration\Enums\ProductIdentifierOptions;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Account;
use Nosto\NostoIntegration\Utils\SearchHelper;
use Nosto\Operation\Category\AnalyticsCategoryTrackingGraphql;
use Nosto\Operation\Search\AnalyticsSearchTrackingGraphql;
use Shopware\Core\Framework\Uuid\Uuid;
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
        private readonly Account\Provider $accountProvider,
        private readonly ConfigProvider $configProvider,
    ) {
    }

    #[Route(
        path: "/nosto/analytics-tracking",
        name: "frontend.nosto.analytics-tracking",
        defaults: [
            'XmlHttpRequest' => true,
        ],
        methods: ["POST"],
    )]
    public function trackAnalytics(Request $request, SalesChannelContext $context): JsonResponse
    {
        $channelId = $context->getSalesChannelId();
        $languageId = $context->getLanguageId();

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

        if ((!isset($data['trackingType']) || $data['trackingType'] == 'category') && !isset($data['category'])) {
            return new JsonResponse([
                'error' => 'Missing category',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $productId = $data['productId'];
            $userAgent = $request->headers->get('User-Agent');
            $merchantId = $this->configProvider->getAccountId($channelId, $languageId);
            $appToken = $this->configProvider->getAppToken(
                $context->getSalesChannelId(),
                $context->getLanguageId(),
            );
            if (!$appToken) {
                throw new Exception('No app token found for the current sales channel and language.');
            }
            $account = $this->accountProvider->get(
                $context->getContext(),
                $context->getSalesChannelId(),
                $context->getLanguageId(),
            );

            $productIdentifier = $this->configProvider->getProductIdentifier($channelId, $languageId);
            $dataSource = DataSource::fromString($data['dataSource']);
            if ($productIdentifier === ProductIdentifierOptions::PRODUCT_NUMBER) {
                $productId = $data['productNumber'];
            }
            $shouldHandleRequest = SearchHelper::shouldHandleRequest(
                $context,
                $this->configProvider,
                $dataSource->getType() === DataSource::CATEGORY,
            );
            if (!$shouldHandleRequest) {
                //it's not an issue at all so lets just give 204 no content
                return new JsonResponse(null, 204);
            }
            $abTests = SearchHelper::getABTestsFromCookie($request);
            if ($dataSource->getType() === DataSource::CATEGORY) {
                $tracker = new AnalyticsCategoryTrackingGraphql(
                    $merchantId,
                    $data['sessionId'],
                    $userAgent,
                    $appToken,
                    $account->getNostoAccount(),
                    $request->getHost(),
                );
                $metadata = new AnalyticsCategoryMetadata(
                    $data["category"] != null ? rtrim($data["category"], "/") : null,
                    $data["categoryId"] ?? null,
                );
                $tracker->click($metadata, $productId, $abTests);
            } elseif ($dataSource->getType() === DataSource::SEARCH) {
                $tracker = new AnalyticsSearchTrackingGraphql(
                    $merchantId,
                    $data['sessionId'],
                    $userAgent,
                    $appToken,
                    $account->getNostoAccount(),
                    $request->getHost(),
                );
                $metadata = new AnalyticsSearchMetadataForGraphql(
                    $data['query'] ?? null,
                    $data['resultId'] ?? vsprintf(
                        '%s%s%s%s-%s%s-%s%s-%s%s-%s%s%s%s%s%s',
                        str_split(Uuid::randomHex(), 2),
                    ),
                    $data['isOrganic'] ?? true,
                    $data['isAutoCorrect'] ?? true,
                    $data['isAutoComplete'] ?? false,
                    $data['isKeyword'] ?? false,
                    $data['isSorted'] ?? true,
                    $data['hasResults'] ?? true,
                    $data['isRefined'] ?? false,
                    $data['searchType'] ?? null,
                    $data['searchTypeReason'] ?? null,
                );
                $tracker->click($metadata, $productId, $abTests);
            } else {
                throw new \InvalidArgumentException('Invalid dataSource');
            }

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
