<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Controller\Storefront;

use Nosto\NostoIntegration\Async\CategorySyncMessage;
use Nosto\NostoIntegration\Async\OrderSyncMessage;
use Nosto\NostoIntegration\Async\ProductSyncMessage;
use Nosto\NostoIntegration\Model\Nosto\Entity\Category\Builder as CategoryBuilder;
use Nosto\NostoIntegration\Model\Nosto\Entity\Order\Builder as OrderBuilder;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\Provider;
use Nosto\NostoIntegration\Model\Operation\CategorySyncHandler;
use Nosto\NostoIntegration\Model\Operation\OrderSyncHandler;
use Nosto\NostoIntegration\Model\Operation\ProductSyncHandler;
use Nosto\NostoIntegration\Service\NostoMonitoringAuthService;
use Nosto\NostoIntegration\Service\NostoMonitoringProductDebug;
use Nosto\Scheduler\Model\Job\JobResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
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
        private readonly NostoMonitoringProductDebug $nostoMonitoringProductDebug,
        NostoMonitoringAuthService $authService,
        private readonly SalesChannelRepository $salesChannelProductRepository,
        private readonly EntityRepository $orderRepository,
        private readonly EntityRepository $categoryRepository,
        private readonly ProductSyncHandler $productSyncHandler,
        private readonly OrderSyncHandler $orderSyncHandler,
        private readonly CategorySyncHandler $categorySyncHandler,
        private readonly Provider $productProvider,
        private readonly CategoryBuilder $nostoCategoryBuilder,
        private readonly OrderBuilder $nostoOrderbuilder,
        private readonly AbstractSalesChannelContextFactory $salesChannelContextFactory,
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
    public function showProduct(Request $request, SalesChannelContext $salesChannelContext): Response|RedirectResponse
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

            $product = $this->salesChannelProductRepository->search($criteria, $salesChannelContext)->first();

            if (!$product) {
                $request->getSession()->getFlashBag()->add('error', "Product with ID '{$productId}' not found.");
                return $this->redirectToRoute('nosto-monitoring.manage-operations');
            }

            $nostoProduct = $this->nostoMonitoringProductDebug->execute(
                new ProductSyncMessage(
                    Uuid::randomHex(),
                    Uuid::randomHex(),
                    [
                        $productId => $productId,
                    ],
                    $salesChannelContext->getContext(),
                    'Product Debug',
                    $salesChannelContext->getSalesChannelId(),
                    $salesChannelContext->getLanguageId(),
                ),
            );

            $products = $nostoProduct->getMessages();

            $productsArray = array_map(function ($product) {
                $reflect = new \ReflectionClass($product);
                $props = $reflect->getProperties();

                $data = [];

                foreach ($props as $prop) {
                    $prop->setAccessible(true);
                    $data[$prop->getName()] = $prop->getValue($product);
                }

                return $data;
            }, $products);

            return $this->renderStorefront(
                '@NostoMonitoringController/storefront/page/nosto-monitoring/debug-product.html.twig',
                [
                    'product' => $productsArray,
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
    public function showCategory(
        Request $request,
        SalesChannelContext $salesChannelContext,
    ): Response|RedirectResponse {
        if ($redirect = $this->requireAuthentication($request)) {
            return $redirect;
        }

        $categoryId = $request->request->get('categoryId') ?? $request->query->get('categoryId');
        if (!$categoryId) {
            $request->getSession()->getFlashBag()->add('error', 'Category ID is required.');
            return $this->redirectToRoute('nosto-monitoring.manage-operations');
        }

        $token = $request->getSession()->get('token') ?? Uuid::randomHex();
        $languageId = $request->request->get('languageId');
        $salesChannelId = $request->request->get('salesChannelId');

        if ($languageId && $salesChannelId) {
            $salesChannelContext = $this->salesChannelContextFactory->create(
                $token,
                $salesChannelId,
                [
                    'languageId' => $languageId,
                ],
            );
        }

        try {
            $criteria = new Criteria([$categoryId]);
            $criteria->getAssociation('seoUrls');

            $category = $this->categoryRepository->search($criteria, $salesChannelContext->getContext())->first();

            if (!$category) {
                $request->getSession()->getFlashBag()->add('error', "Category with ID '{$categoryId}' not found.");
                return $this->redirectToRoute('nosto-monitoring.manage-operations');
            }

            $nostoCategory = $this->nostoCategoryBuilder->build(
                $category,
                $salesChannelContext,
            );

            $reflect = new \ReflectionClass($nostoCategory);
            $props = $reflect->getProperties();

            $categoryData = [];

            foreach ($props as $prop) {
                $prop->setAccessible(true);
                $categoryData[$prop->getName()] = $prop->getValue($nostoCategory);
            }

            return $this->renderStorefront(
                '@NostoMonitoringController/storefront/page/nosto-monitoring/debug-category.html.twig',
                [
                    'category' => $categoryData,
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
    public function showOrder(
        Request $request,
        Context $context,
        SalesChannelContext $salesChannelContext,
    ): Response|RedirectResponse {
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
            $criteria->addAssociation('stateMachineState');
            $criteria->addAssociation('orderCustomer');
            $criteria->addAssociation('currency');
            $criteria->addAssociation('addresses');
            $criteria->addAssociation('billingAddress');
            $criteria->addAssociation('transactions.paymentMethod');
            $criteria->addAssociation('lineItems.orderLineItem.product');

            $order = $this->orderRepository->search($criteria, $context)->first();

            if (!$order) {
                $request->getSession()->getFlashBag()->add('error', "Order with ID '{$orderId}' not found.");
                return $this->redirectToRoute('nosto-monitoring.manage-operations');
            }

            $nostoOrder = $this->nostoOrderbuilder->build(
                $order,
                $salesChannelContext,
            );

            $reflect = new \ReflectionClass($nostoOrder);
            $props = $reflect->getProperties();

            $orderData = [];

            foreach ($props as $prop) {
                $prop->setAccessible(true);
                $orderData[$prop->getName()] = $prop->getValue($nostoOrder);
            }

            return $this->renderStorefront(
                '@NostoMonitoringController/storefront/page/nosto-monitoring/debug-order.html.twig',
                [
                    'order' => $orderData,
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
            $context->getSalesChannelId(),
            $context->getLanguageId(),
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
