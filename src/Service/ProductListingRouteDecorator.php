<?php
namespace Od\NostoIntegration\Service;

use Shopware\Core\Content\Product\SalesChannel\Listing\AbstractProductListingRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ProductListingRouteDecorator extends \Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRoute
{
    public function __construct(
        ProductListingLoader $listingLoader,
        EventDispatcherInterface $eventDispatcher,
        EntityRepository $categoryRepository,
        ProductStreamBuilderInterface $productStreamBuilder,
        \Shopware\Core\Framework\Store\Services\InstanceService $instanceService
    ) {
        if ($instanceService->getShopwareVersion() >= '6.5.3') {
            parent::__construct($listingLoader, $categoryRepository, $productStreamBuilder);
        } else {
            parent::__construct($listingLoader, $eventDispatcher, $categoryRepository, $productStreamBuilder);
        }
    }
}
