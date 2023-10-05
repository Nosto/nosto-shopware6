<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Entity\Product\Event;

use Nosto\Model\Product\Product as NostoProduct;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class NostoProductBuiltEvent extends NestedEvent implements ShopwareSalesChannelEvent
{
    private ProductEntity $product;
    private NostoProduct $nostoProduct;
    private SalesChannelContext $salesChannelContext;

    public function __construct(
        ProductEntity $product,
        NostoProduct $nostoProduct,
        SalesChannelContext $salesChannelContext
    ) {
        $this->product = $product;
        $this->nostoProduct = $nostoProduct;
        $this->salesChannelContext = $salesChannelContext;
    }

    public function getProduct(): ProductEntity
    {
        return $this->product;
    }

    public function getNostoProduct(): NostoProduct
    {
        return $this->nostoProduct;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}
