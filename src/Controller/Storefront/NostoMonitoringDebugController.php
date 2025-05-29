<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Controller\Storefront;

use Nosto\NostoIntegration\Async\CategorySyncMessage;
use Nosto\NostoIntegration\Async\OrderSyncMessage;
use Nosto\NostoIntegration\Async\ProductSyncMessage;
use Nosto\NostoIntegration\Model\Operation\CategorySyncHandler;
use Nosto\NostoIntegration\Model\Operation\OrderSyncHandler;
use Nosto\NostoIntegration\Model\Operation\ProductSyncHandler;
use Nosto\NostoIntegration\Service\NostoMonitoringAuthService;
use Nosto\Scheduler\Model\Job\JobResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: [
    '_routeScope' => ['storefront'],
])]
class NostoMonitoringDebugController extends AbstractNostoMonitoringController
{
    public function __construct(
        NostoMonitoringAuthService $authService,
        private readonly EntityRepository $productRepository,
        private readonly EntityRepository $orderRepository,
        private readonly EntityRepository $categoryRepository,
        private readonly ProductSyncHandler $productSyncHandler,
        private readonly OrderSyncHandler $orderSyncHandler,
        private readonly CategorySyncHandler $categorySyncHandler,
    ) {
        parent::__construct($authService);
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
        if ($redirect = $this->requireAuthentication($request)) {
            return $redirect;
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
                'translations',
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
                    'resourceType' => 'product',
                ],
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
        if ($redirect = $this->requireAuthentication($request)) {
            return $redirect;
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
                'customFields',
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
                    'resourceType' => 'category',
                ],
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
        if ($redirect = $this->requireAuthentication($request)) {
            return $redirect;
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
                'currency',
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
                    'resourceType' => 'order',
                ],
            );
        } catch (\Exception $e) {
            $request->getSession()->getFlashBag()->add('error', 'Error loading order: ' . $e->getMessage());
            return $this->redirectToRoute('nosto-monitoring.manage-operations');
        }
    }

    #[Route(
        path: '/nosto-monitoring/debug-sync-product',
        name: 'nosto-monitoring.debug-sync-product',
        options: [
            "seo" => "false",
        ],
        methods: ['POST'],
    )]
    public function debugSyncProduct(Request $request, SalesChannelContext $context): RedirectResponse
    {
        if ($redirect = $this->requireAuthentication($request)) {
            return $redirect;
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

        $this->addFlashMessages($request, $result);

        return $this->redirectToRoute('nosto-monitoring.show-product', [
            'productId' => $productId,
        ]);
    }

    #[Route(
        path: '/nosto-monitoring/debug-sync-order',
        name: 'nosto-monitoring.debug-sync-order',
        options: [
            "seo" => "false",
        ],
        methods: ['POST'],
    )]
    public function debugSyncOrder(Request $request, SalesChannelContext $context): RedirectResponse
    {
        if ($redirect = $this->requireAuthentication($request)) {
            return $redirect;
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

        $this->addFlashMessages($request, $result);

        return $this->redirectToRoute('nosto-monitoring.show-order', [
            'orderId' => $orderId,
        ]);
    }

    #[Route(
        path: '/nosto-monitoring/debug-sync-category',
        name: 'nosto-monitoring.debug-sync-category',
        options: [
            "seo" => "false",
        ],
        methods: ['POST'],
    )]
    public function debugSyncCategory(Request $request, SalesChannelContext $context): RedirectResponse
    {
        if ($redirect = $this->requireAuthentication($request)) {
            return $redirect;
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

        $this->addFlashMessages($request, $result);

        return $this->redirectToRoute('nosto-monitoring.show-category', [
            'categoryId' => $categoryId,
        ]);
    }

    private function addFlashMessages(Request $request, JobResult $result): void
    {
        foreach ($result->getMessages() as $jobMessage) {
            $request->getSession()->getFlashBag()->add('success', $jobMessage->getMessage());
        }

        foreach ($result->getErrors() as $jobMessage) {
            $request->getSession()->getFlashBag()->add('error', $jobMessage->getMessage());
        }

        if (empty($result->getMessages()) && empty($result->getErrors())) {
            $request->getSession()->getFlashBag()->add('success', 'Sync completed successfully.');
        }
    }
}
