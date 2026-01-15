<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Entity\Order\Item;

use Exception;
use Nosto\Model\Cart\LineItem as NostoLineItem;
use Nosto\NostoIntegration\Enums\ProductIdentifierOptions;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\ProductHelper;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\ProductProviderInterface;
use Nosto\NostoIntegration\Utils\ProductTaggingHelper;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Nosto\NostoIntegration\Utils\NostoCriteriaFactory;

class Builder
{
    public function build(
        OrderLineItemEntity $item,
        CurrencyEntity $currency,
        EntityRepository $productRepository,
        SalesChannelContext $context,
        ConfigProvider $configProvider,
        SystemConfigService $systemConfigService,
        ProductHelper $productHelper,
        ProductProviderInterface $productProvider,
    ): NostoLineItem {
        $criteria = NostoCriteriaFactory::createWithIds([$item->getProductId()]);
        $criteria->addAssociation('children');
        /** @var ProductEntity|null $product */
        $product = $productRepository->search($criteria, $context->getContext())->first();
        $criteria = NostoCriteriaFactory::createWithIds([$product->getParentId() != null ? $product->getParentId() : $product->getId()]);
        $criteria->addAssociation('children');
        /** @var ProductEntity|null $parentProduct */
        $parentProduct = $productRepository->search($criteria, $context->getContext())->first();
        $skuId = $item->getPayload()['productNumber'];
        if ($configProvider->getProductIdentifier(
            $context->getSalesChannelId(),
            $context->getLanguageId(),
        ) === ProductIdentifierOptions::PRODUCT_ID) {
            $skuId = $item->getProductId();
        }
        $productTaggingHelper = new ProductTaggingHelper(
            $systemConfigService,
            $configProvider,
            $productProvider,
            $productHelper,
        );
        $productId = $productTaggingHelper->findProductId($context, $parentProduct, $product);

        $nostoItem = new NostoLineItem();
        $nostoItem->setPriceCurrencyCode($currency->getIsoCode());
        $nostoItem->setProductId($productId);
        $nostoItem->setQuantity($item->getQuantity());
        $nostoItem->setSkuId($skuId);
        $nostoItem->setName($product->getTranslation("name"));

        try {
            $price = $item->getUnitPrice();
            $nostoItem->setPrice($price);
        } catch (Exception) {
            $nostoItem->setPrice(0);
        }

        return $nostoItem;
    }

    public function getProductId(
        ProductEntity $productEntity,
        ProductIdentifierOptions $productIdentifierOptions,
    ): string|null {
        if ($productIdentifierOptions === ProductIdentifierOptions::PRODUCT_NUMBER) {
            return $productEntity->getProductNumber();
        } else {
            return $productEntity->getId();
        }
    }
}
