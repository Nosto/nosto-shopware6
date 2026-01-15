<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Api\Controller;

use Nosto\NostoIntegration\Enums\ProductIdentifierOptions;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Nosto\NostoIntegration\Utils\NostoCriteriaFactory;

#[Route(
    defaults: [
        '_routeScope' => ['storefront'],
    ],
)]
class NostoMulticurrencyController extends AbstractController
{
    public function __construct(
        private readonly ConfigProvider $configProvider,
        private readonly SalesChannelRepository $productRepository,
    ) {
    }

    #[Route(
        path: "/nosto/multi-currency",
        name: "storefront.nosto.product-currency",
        defaults: [
            'XmlHttpRequest' => true,
        ],
        methods: ["GET"],
    )]
    public function getProductsInContextCurrency(Request $request, SalesChannelContext $context): JsonResponse
    {
        $channelId = $context->getSalesChannelId();
        $languageId = $context->getLanguageId();

        $productIds = $request->query->get('productIds');
        if (empty($productIds)) {
            return new JsonResponse([
                'error' => 'No productIds provided',
            ], Response::HTTP_BAD_REQUEST);
        }
        $productIdentifier = $this->configProvider->getProductIdentifier($channelId, $languageId);
        $productIdsArray = explode(',', $productIds);
        $productIdsArray = array_map('trim', $productIdsArray);
        $productIdsArray = array_filter($productIdsArray);
        if (count($productIdsArray) > 50) {
            return new JsonResponse([
                'error' => 'Too many product Ids provided limit is 50',
            ], Response::HTTP_BAD_REQUEST);
        }
        $criteria = NostoCriteriaFactory::create();
        if ($productIdentifier == ProductIdentifierOptions::PRODUCT_NUMBER) {
            $criteria->addFilter(new EqualsAnyFilter('productNumber', $productIdsArray));
        } else {
            $criteria->addFilter(new EqualsAnyFilter('id', $productIdsArray));
        }
        $criteria->addAssociation('price');
        $products = $this->productRepository->search($criteria, $context);
        $productData = null;

        if ($products->count() == 0) {
            return new JsonResponse([
                'error' => 'No products found',
            ], Response::HTTP_BAD_REQUEST);
        }

        /** @var SalesChannelProductEntity $product */
        foreach ($products as $product) {
            $productData[] = [
                'id' => $productIdentifier == ProductIdentifierOptions::PRODUCT_NUMBER ? $product->getProductNumber() : $product->getId(),
                'name' => $product->getName(),
                'description' => $product->getDescription(),
                'price' => $product->getCalculatedPrice()->getTotalPrice(),
                'listPrice' => $product->getCalculatedPrice()->getListPrice(),
                'currencySymbol' => $context->getCurrency()->getSymbol(),
                'currency' => $context->getCurrency()->getShortName(),
            ];
        }
        return new JsonResponse([
            'success' => true,
            'products' => $productData,
        ]);
    }
}
