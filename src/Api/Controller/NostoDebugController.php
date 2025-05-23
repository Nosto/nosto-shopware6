<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Api\Controller;

use Nosto\NostoIntegration\Async\CategorySyncMessage;
use Nosto\NostoIntegration\Async\OrderSyncMessage;
use Nosto\NostoIntegration\Async\ProductSyncMessage;
use Nosto\NostoIntegration\Model\Operation\CategorySyncHandler;
use Nosto\NostoIntegration\Model\Operation\OrderSyncHandler;
use Nosto\NostoIntegration\Model\Operation\ProductSyncHandler;
use Nosto\Scheduler\Model\Job\Message\JobMessage;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(
    defaults: [
        '_routeScope' => ['storefront'],
    ],
)]
class NostoDebugController extends AbstractController
{
    public function __construct(
        private readonly ProductSyncHandler $productSyncHandler,
        private readonly OrderSyncHandler $orderSyncHandler,
        private readonly CategorySyncHandler $categorySyncHandler,
    ) {
    }

    #[Route(path: '/nosto-product-debug', name: 'storefront.nosto_integration.product_debug', methods: ['GET'])]
    public function debug(Request $request, SalesChannelContext $context): JsonResponse
    {
        $productId = $request->get('productId');
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

        return new JsonResponse([
            'messages' => array_map(static fn (JobMessage $message) => $message->getMessage(), $result->getMessages()),
            'errors' => array_map(static fn (JobMessage $message) => $message->getMessage(), $result->getErrors()),
        ]);
    }

    #[Route(path: '/nosto-order-debug', name: 'storefront.nosto_integration.order_debug', methods: ['GET'])]
    public function debugOrder(Request $request, SalesChannelContext $context): JsonResponse
    {
        $orderId = $request->get('orderId');
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

        return new JsonResponse([
            'messages' => array_map(static fn (JobMessage $message) => $message->getMessage(), $result->getMessages()),
            'errors' => array_map(static fn (JobMessage $message) => $message->getMessage(), $result->getErrors()),
        ]);
    }

    #[Route(path: '/nosto-category-debug', name: 'storefront.nosto_integration.category_debug', methods: ['GET'])]
    public function debugCategory(Request $request, SalesChannelContext $context): JsonResponse
    {
        $categoryId = $request->get('categoryId');
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

        return new JsonResponse([
            'messages' => array_map(static fn (JobMessage $message) => $message->getMessage(), $result->getMessages()),
            'errors' => array_map(static fn (JobMessage $message) => $message->getMessage(), $result->getErrors()),
        ]);
    }
}
