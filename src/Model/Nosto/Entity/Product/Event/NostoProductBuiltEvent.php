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
    public function __construct(
        private readonly ProductEntity $product,
        private readonly NostoProduct $nostoProduct,
        private readonly SalesChannelContext $salesChannelContext,
    ) {
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
