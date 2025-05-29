<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Controller\Storefront;

use Nosto\NostoIntegration\Async\CategorySyncMessage;
use Nosto\NostoIntegration\Async\OrderSyncMessage;
use Nosto\NostoIntegration\Async\ProductSyncMessage;
use Nosto\NostoIntegration\Model\Config\NostoConfigCollection;
use Nosto\NostoIntegration\Model\Config\NostoConfigService;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\NostoMonitoringHelper;
use Nosto\NostoIntegration\Model\Operation\CategorySyncHandler;
use Nosto\NostoIntegration\Model\Operation\OrderSyncHandler;
use Nosto\NostoIntegration\Model\Operation\ProductSyncHandler;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
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
    public const ACCESS_KEY_PROPERTY = 'nostoAccessKey';

    private ParameterBagInterface $parameterBag;

    public function __construct(
        private readonly NostoMonitoringHelper $nostoMonitoringHelper,
        ParameterBagInterface $parameterBag,
        private readonly NostoMonitoringAuthService $authService,
        private readonly ConfigProvider $nostoConfigProvider,
        private readonly EntityRepository $productRepository,
        private readonly EntityRepository $orderRepository,
        private readonly EntityRepository $categoryRepository,
        private readonly ProductSyncHandler $productSyncHandler,
        private readonly OrderSyncHandler $orderSyncHandler,
        private readonly CategorySyncHandler $categorySyncHandler,
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
        $accessKey = $request->request->get(self::ACCESS_KEY_PROPERTY);
        if (!is_string($accessKey) || empty($accessKey)) {
            return $this->redirectToRoute('nosto-monitoring.access-page');
        }

        $isValid = $this->authService->validateAccessKey($accessKey);

        if (!$isValid) {
            return $this->redirectToRoute('nosto-monitoring.access-page');
        }

        $this->authService->authenticate($request->getSession());
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
        if (!$this->authService->isAuthenticated($request->getSession())) {
            return $this->redirectToRoute('nosto-monitoring.access-page');
        }

        return $this->renderStorefront(
            '@NostoMonitoringController/storefront/page/nosto-monitoring/manage-operations.html.twig',
            [
                'nostoConfig' => $this->getNostoConfiguration($request->get('salesChannelId'), $request->get('languageId'))
            ]
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
        if (!$this->authService->isAuthenticated($request->getSession())) {
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
        if (!$this->authService->isAuthenticated($request->getSession())) {
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
        if (!$this->authService->isAuthenticated($request->getSession())) {
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

        $response = new BinaryFileResponse($zipFile, Response::HTTP_OK, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="logs_archive.zip"',
        ]);

        $response->deleteFileAfterSend();

        return $response;
    }

    #[Route(
        path: "/nosto-monitoring/logout",
        name: "nosto-monitoring.logout",
        options: ["seo" => "false"],
        methods: ["POST"],
    )]
    public function logout(Request $request): RedirectResponse
    {
        $this->authService->logout($request->getSession());

        return $this->redirectToRoute('nosto-monitoring.access-page');
    }

    private function getNostoConfiguration(?string $salesChannelId, ?string $languageId): array
    {
        return [
            // Account Settings
            'accountEnabled' => $this->nostoConfigProvider->isAccountEnabled($salesChannelId, $languageId),
            'accountId' => $this->nostoConfigProvider->getAccountId($salesChannelId, $languageId),
            'accountName' => $this->nostoConfigProvider->getAccountName($salesChannelId, $languageId),

            // API Tokens
            'productToken' => $this->nostoConfigProvider->getProductToken($salesChannelId, $languageId),
            'emailToken' => $this->nostoConfigProvider->getEmailToken($salesChannelId, $languageId),
            'appToken' => $this->nostoConfigProvider->getAppToken($salesChannelId, $languageId),
            'searchToken' => $this->nostoConfigProvider->getSearchToken($salesChannelId, $languageId),

            // Personalized Search & Category Merchandising
            'enablePersonalizedSearch' => $this->nostoConfigProvider->isSearchEnabled($salesChannelId, $languageId),
            'enableCategoryMerchandising' => $this->nostoConfigProvider->isNavigationEnabled($salesChannelId, $languageId),

            // General Settings
            'initializeNostoAfterInteraction' => $this->nostoConfigProvider->shouldInitializeNostoAfterInteraction($salesChannelId, $languageId),
            'domainId' => $this->nostoConfigProvider->getDomainId($salesChannelId, $languageId),

            // Tags Assignment
            'selectedCustomFields' => $this->nostoConfigProvider->getSelectedCustomFields($salesChannelId, $languageId),
            'tag1' => $this->nostoConfigProvider->getTagFieldKey(1, $salesChannelId, $languageId),
            'tag2' => $this->nostoConfigProvider->getTagFieldKey(2, $salesChannelId, $languageId),
            'tag3' => $this->nostoConfigProvider->getTagFieldKey(3, $salesChannelId, $languageId),
            'googleCategory' => $this->nostoConfigProvider->getGoogleCategory($salesChannelId, $languageId),

            // Feature Flags (these return enum values)
            'productIdentifier' => $this->nostoConfigProvider->getProductIdentifier($salesChannelId, $languageId)->value,
            'ratingsReviews' => $this->nostoConfigProvider->getRatingReviews($salesChannelId, $languageId)->value,
            'stockField' => $this->nostoConfigProvider->getStockField($salesChannelId, $languageId)->value,
            'crossSellingSync' => $this->nostoConfigProvider->getCrossSellingSyncOption($salesChannelId, $languageId)->value,
            'categoryNaming' => $this->nostoConfigProvider->getCategoryNamingOption($salesChannelId, $languageId)->value,
            'categoryBlocklist' => $this->nostoConfigProvider->getCategoryBlocklist($salesChannelId, $languageId),

            // Toggle Features
            'enableVariations' => $this->nostoConfigProvider->isEnabledVariations($salesChannelId, $languageId),
            'enableProductProperties' => $this->nostoConfigProvider->isEnabledProductProperties($salesChannelId, $languageId),
            'enableAlternateImages' => $this->nostoConfigProvider->isEnabledAlternateImages($salesChannelId, $languageId),
            'enableInventoryLevels' => $this->nostoConfigProvider->isEnabledInventoryLevels($salesChannelId, $languageId),
            'enableCustomerDataToNosto' => $this->nostoConfigProvider->isEnabledCustomerDataToNosto($salesChannelId, $languageId),
            'enableSyncInactiveProducts' => $this->nostoConfigProvider->isEnabledSyncInactiveProducts($salesChannelId, $languageId),
            'enableProductPublishedDateTagging' => $this->nostoConfigProvider->isEnabledProductPublishedDateTagging($salesChannelId, $languageId),
            'enableReloadRecommendations' => $this->nostoConfigProvider->isEnabledReloadRecommendationsAfterAdding($salesChannelId, $languageId),
            'enableProductLabellingSync' => $this->nostoConfigProvider->isEnabledProductLabellingSync($salesChannelId, $languageId),
            'enableStoreAbandonedCartData' => $this->nostoConfigProvider->isEnabledStoreAbandonedCartData($salesChannelId, $languageId),
            'enableIgnoreCookieConsent' => $this->nostoConfigProvider->isEnabledIgnoreCookieConsent($salesChannelId, $languageId),
            'enableSyncFirstAvailableVariant' => $this->nostoConfigProvider->isEnabledSyncFirstAvailableVariant($salesChannelId, $languageId),
            'enableSearchImpressions' => $this->nostoConfigProvider->isEnabledSearchImpressions($salesChannelId, $languageId),

            // Daily Sync & Cleanup Settings
            'dailyProductSyncEnabled' => $this->nostoConfigProvider->isDailyProductSyncEnabled($salesChannelId, $languageId),
//            'dailyProductSyncTime' => $this->nostoConfigProvider->getDailyProductSyncTime($salesChannelId, $languageId),
            'oldJobCleanupEnabled' => $this->nostoConfigProvider->isOldJobCleanupEnabled($salesChannelId, $languageId),
            'oldJobCleanupPeriod' => $this->nostoConfigProvider->getOldJobCleanupPeriod($salesChannelId, $languageId),
            'oldNostoDataCleanupEnabled' => $this->nostoConfigProvider->isOldNostoDataCleanupEnabled($salesChannelId, $languageId),
            'oldNostoDataCleanupPeriod' => $this->nostoConfigProvider->getOldNostoDataCleanupPeriod($salesChannelId, $languageId),

            // Full config array for debugging/additional data
            'fullConfig' => $this->nostoConfigProvider->toArray($salesChannelId, $languageId),
        ];
    }

    #[Route(
        path: "/nosto-monitoring/show-product",
        name: "nosto-monitoring.show-product",
        options: [
            "seo" => "false",
        ],
        methods: ["GET", "POST"],
    )]
    public function showProduct(Request $request, Context $context): Response|RedirectResponse
    {
        if (!$this->authService->isAuthenticated($request->getSession())) {
            return $this->redirectToRoute('nosto-monitoring.access-page');
        }

        $productId = $request->request->get('productId') ?? $request->query->get('productId');

        if (!$productId) {
            $request->getSession()->getFlashBag()->add('error', 'Product ID is required.');
            return $this->redirectToRoute('nosto-monitoring.manage-operations');
        }

        try {
            $criteria = new Criteria([$productId]);
            $criteria->addAssociations([
                'categories',
                'properties',
                'options',
                'configuratorSettings',
                'prices',
                'cover',
                'media',
                'crossSellings',
                'translations'
            ]);

            $product = $this->productRepository->search($criteria, $context)->first();

            if (!$product) {
                $request->getSession()->getFlashBag()->add('error', "Product with ID '{$productId}' not found.");
                return $this->redirectToRoute('nosto-monitoring.manage-operations');
            }

            return $this->renderStorefront(
                '@NostoMonitoringController/storefront/page/nosto-monitoring/debug-product.html.twig',
                [
                    'product' => $product,
                    'productId' => $productId,
                    'resourceType' => 'product'
                ]
            );

        } catch (\Exception $e) {
            $request->getSession()->getFlashBag()->add('error', 'Error loading product: ' . $e->getMessage());
            return $this->redirectToRoute('nosto-monitoring.manage-operations');
        }
    }

    #[Route(
        path: "/nosto-monitoring/show-category",
        name: "nosto-monitoring.show-category",
        options: [
            "seo" => "false",
        ],
        methods: ["POST", "GET"],
    )]
    public function showCategory(Request $request, Context $context): Response|RedirectResponse
    {
        if (!$this->authService->isAuthenticated($request->getSession())) {
            return $this->redirectToRoute('nosto-monitoring.access-page');
        }

        $categoryId = $request->request->get('categoryId') ?? $request->query->get('categoryId');
        if (!$categoryId) {
            $request->getSession()->getFlashBag()->add('error', 'Category ID is required.');
            return $this->redirectToRoute('nosto-monitoring.manage-operations');
        }

        try {
            $criteria = new Criteria([$categoryId]);
            $criteria->addAssociations([
                'parent',
                'children',
                'media',
                'products',
                'translations',
                'customFields'
            ]);

            $category = $this->categoryRepository->search($criteria, $context)->first();

            if (!$category) {
                $request->getSession()->getFlashBag()->add('error', "Category with ID '{$categoryId}' not found.");
                return $this->redirectToRoute('nosto-monitoring.manage-operations');
            }

            return $this->renderStorefront(
                '@NostoMonitoringController/storefront/page/nosto-monitoring/debug-category.html.twig',
                [
                    'category' => $category,
                    'categoryId' => $categoryId,
                    'resourceType' => 'category'
                ]
            );

        } catch (\Exception $e) {
            $request->getSession()->getFlashBag()->add('error', 'Error loading category: ' . $e->getMessage());
            return $this->redirectToRoute('nosto-monitoring.manage-operations');
        }
    }

    #[Route(
        path: "/nosto-monitoring/show-order",
        name: "nosto-monitoring.show-order",
        options: [
            "seo" => "false",
        ],
        methods: ["POST", "GET"],
    )]
    public function showOrder(Request $request, Context $context): Response|RedirectResponse
    {
        if (!$this->authService->isAuthenticated($request->getSession())) {
            return $this->redirectToRoute('nosto-monitoring.access-page');
        }

        $orderId = $request->request->get('orderId') ?? $request->query->get('orderId');
        if (!$orderId) {
            $request->getSession()->getFlashBag()->add('error', 'Order ID is required.');
            return $this->redirectToRoute('nosto-monitoring.manage-operations');
        }

        try {
            $criteria = new Criteria([$orderId]);
            $criteria->addAssociations([
                'lineItems',
                'lineItems.product',
                'addresses',
                'customer',
                'orderCustomer',
                'deliveries',
                'transactions',
                'stateMachineState',
                'currency'
            ]);

            $order = $this->orderRepository->search($criteria, $context)->first();

            if (!$order) {
                $request->getSession()->getFlashBag()->add('error', "Order with ID '{$orderId}' not found.");
                return $this->redirectToRoute('nosto-monitoring.manage-operations');
            }

            return $this->renderStorefront(
                '@NostoMonitoringController/storefront/page/nosto-monitoring/debug-order.html.twig',
                [
                    'order' => $order,
                    'orderId' => $orderId,
                    'resourceType' => 'order'
                ]
            );

        } catch (\Exception $e) {
            $request->getSession()->getFlashBag()->add('error', 'Error loading order: ' . $e->getMessage());
            return $this->redirectToRoute('nosto-monitoring.manage-operations');
        }
    }


    #[Route(
        path: '/nosto-monitoring/debug-sync-product',
        name: 'nosto-monitoring.debug-sync-product',
        options: ["seo" => "false"],
        methods: ['POST']
    )]
    public function debugSyncProduct(Request $request, SalesChannelContext $context): RedirectResponse
    {
        if (!$this->authService->isAuthenticated($request->getSession())) {
            return $this->redirectToRoute('nosto-monitoring.access-page');
        }

        $productId = $request->request->get('productId');

        if (!$productId) {
            $request->getSession()->getFlashBag()->add('error', 'Product ID is required for sync.');
            return $this->redirectToRoute('nosto-monitoring.manage-operations');
        }

        $message = new ProductSyncMessage(
            Uuid::randomHex(),
            Uuid::randomHex(),
            [
                $productId => $productId,
            ],
            $context->getContext(),
            'Product Debug',
        );

        $result = $this->productSyncHandler->execute($message);

        foreach ($result->getMessages() as $jobMessage) {
            $request->getSession()->getFlashBag()->add('success', $jobMessage->getMessage());
        }

        foreach ($result->getErrors() as $jobMessage) {
            $request->getSession()->getFlashBag()->add('error', $jobMessage->getMessage());
        }

        if (empty($result->getMessages()) && empty($result->getErrors())) {
            $request->getSession()->getFlashBag()->add('success', 'Product sync completed successfully.');
        }

        return $this->redirectToRoute('nosto-monitoring.show-product', ['productId' => $productId]);
    }

    #[Route(
        path: '/nosto-monitoring/debug-sync-order',
        name: 'nosto-monitoring.debug-sync-order',
        options: ["seo" => "false"],
        methods: ['POST']
    )]
    public function debugSyncOrder(Request $request, SalesChannelContext $context): RedirectResponse
    {
        if (!$this->authService->isAuthenticated($request->getSession())) {
            return $this->redirectToRoute('nosto-monitoring.access-page');
        }
        $orderId = $request->request->get('orderId');
        if (!$orderId) {
            $request->getSession()->getFlashBag()->add('error', 'Order ID is required for sync.');
            return $this->redirectToRoute('nosto-monitoring.manage-operations');
        }

        $message = new OrderSyncMessage(
            Uuid::randomHex(),
            Uuid::randomHex(),
            [
                $orderId => $orderId,
            ],
            [
                $orderId => $orderId,
            ],
            $context->getContext(),
            'Order Debug',
        );

        $result = $this->orderSyncHandler->execute($message);

        foreach ($result->getMessages() as $jobMessage) {
            $request->getSession()->getFlashBag()->add('success', $jobMessage->getMessage());
        }

        foreach ($result->getErrors() as $jobMessage) {
            $request->getSession()->getFlashBag()->add('error', $jobMessage->getMessage());
        }

        if (empty($result->getMessages()) && empty($result->getErrors())) {
            $request->getSession()->getFlashBag()->add('success', 'Order sync completed successfully.');
        }

        return $this->redirectToRoute('nosto-monitoring.show-order', ['orderId' => $orderId]);
    }

    #[Route(
        path: '/nosto-monitoring/debug-sync-category',
        name: 'nosto-monitoring.debug-sync-category',
        options: ["seo" => "false"],
        methods: ['POST']
    )]
    public function debugSyncCategory(Request $request, SalesChannelContext $context): RedirectResponse
    {
        if (!$this->authService->isAuthenticated($request->getSession())) {
            return $this->redirectToRoute('nosto-monitoring.access-page');
        }
        $categoryId = $request->request->get('categoryId');

        if (!$categoryId) {
            $request->getSession()->getFlashBag()->add('error', 'Category ID is required for sync.');
            return $this->redirectToRoute('nosto-monitoring.manage-operations');
        }

        $message = new CategorySyncMessage(
            Uuid::randomHex(),
            Uuid::randomHex(),
            [
                $categoryId => $categoryId,
            ],
            $context->getContext(),
            'Category Debug',
        );

        $result = $this->categorySyncHandler->execute($message);

        foreach ($result->getMessages() as $jobMessage) {
            $request->getSession()->getFlashBag()->add('success', $jobMessage->getMessage());
        }

        // Add error messages
        foreach ($result->getErrors() as $jobMessage) {
            $request->getSession()->getFlashBag()->add('error', $jobMessage->getMessage());
        }

        // If no messages, add a default success message
        if (empty($result->getMessages()) && empty($result->getErrors())) {
            $request->getSession()->getFlashBag()->add('success', 'Category sync completed successfully.');
        }

        return $this->redirectToRoute('nosto-monitoring.show-category', ['categoryId' => $categoryId]);
    }
}
