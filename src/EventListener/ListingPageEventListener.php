<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\EventListener;

use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Service\CategoryMerchandising\MerchandisingSearchApi;
use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ListingPageEventListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly ConfigProvider $configProvider,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductListingResultEvent::class => [
                ['removeScoreSorting', -110],
            ],
        ];
    }

    public function removeScoreSorting(ProductListingResultEvent $event): void
    {
        if ($this->configProvider->isMerchEnabled(
            $event->getSalesChannelContext()->getSalesChannelId(),
            $event->getSalesChannelContext()->getLanguageId(),
        )) {
            return;
        }
        $sortings = $event->getResult()->getAvailableSortings();
        foreach ($sortings as $sorting) {
            if ($sorting->getKey() === MerchandisingSearchApi::MERCHANDISING_SORTING_KEY) {
                $sortings->remove($sorting->getId());
            }
        }
    }
}
