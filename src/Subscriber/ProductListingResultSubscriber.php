<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Subscriber;

use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Search\Request\Handler\SortHandlers\RecommendationSortingHandler;
use Nosto\NostoIntegration\Utils\SearchHelper;
use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use Shopware\Core\Content\Product\Events\ProductSearchResultEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductListingResultSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ConfigProvider $configProvider,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ProductListingResultEvent::class => 'onProductListingResult',
            ProductSearchResultEvent::class => 'onProductSearchResult',
        ];
    }

    public function onProductListingResult(ProductListingResultEvent $event): void
    {
        $context = $event->getSalesChannelContext();
        $request = $event->getRequest();

        $shouldHandleRequest = SearchHelper::shouldHandleRequest($context, $this->configProvider, true, $request);

        // Only remove merchandising sorting key when Nosto is NOT handling the request
        if (!$shouldHandleRequest) {
            try {
                $event->getResult()->getAvailableSortings()->removeByKey(
                    RecommendationSortingHandler::MERCHANDISING_SORTING_KEY,
                );
            } catch (\Error) {
                // availableSortings may not be initialized
            }
        }
    }

    public function onProductSearchResult(ProductSearchResultEvent $event): void
    {
        $context = $event->getSalesChannelContext();
        $request = $event->getRequest();

        // For search, isNavigationPage is false
        $shouldHandleRequest = SearchHelper::shouldHandleRequest($context, $this->configProvider, false, $request);

        // Only remove merchandising sorting key when Nosto is NOT handling the request
        if (!$shouldHandleRequest) {
            try {
                $event->getResult()->getAvailableSortings()->removeByKey(
                    RecommendationSortingHandler::MERCHANDISING_SORTING_KEY,
                );
            } catch (\Error) {
                // availableSortings may not be initialized
            }
        }
    }
}
