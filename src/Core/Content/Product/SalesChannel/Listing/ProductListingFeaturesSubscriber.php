<?php

namespace Nosto\NostoIntegration\Core\Content\Product\SalesChannel\Listing;

use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSearchResultEvent;
use Shopware\Core\Content\Product\Events\ProductSuggestCriteriaEvent;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingFeaturesSubscriber as ShopwareProductListingFeaturesSubscriber;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductListingFeaturesSubscriber implements EventSubscriberInterface
{
    public function __construct(
        protected readonly ShopwareProductListingFeaturesSubscriber $decorated
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductListingCriteriaEvent::class => 'prepare',
            ProductSuggestCriteriaEvent::class => 'prepare',
            ProductSearchCriteriaEvent::class => [
                ['handleSearchRequest', 100],
            ],
            ProductListingResultEvent::class => 'process',
            ProductSearchResultEvent::class => 'process',
        ];
    }

    public function prepare(ProductListingCriteriaEvent $event): void
    {
        $this->decorated->prepare($event);
    }

    public function process(ProductListingResultEvent $event): void
    {
        $this->decorated->process($event);
    }

    public function handleSearchRequest(ProductSearchCriteriaEvent $event): void
    {
        $limit = $event->getCriteria()->getLimit();
        $this->decorated->prepare($event);

        $limitOverride = $limit ?? $event->getCriteria()->getLimit();

        $event->getCriteria()->setIds(['2a88d9b59d474c7e869d8071649be43c', '11dc680240b04f469ccba354cbf0b967']);

//        $this->findologicSearchService->doSearch($event, $limitOverride);
    }

    public function __call($method, $args)
    {
        if (!method_exists($this->decorated, $method)) {
            return;
        }

        return $this->decorated->{$method}(...$args);
    }
}