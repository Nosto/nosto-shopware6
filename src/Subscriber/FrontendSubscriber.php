<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Subscriber;

use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Struct\Config;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FrontendSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ConfigProvider $configProvider,
    ) {
    }

    /**
     * @return string[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            HeaderPageletLoadedEvent::class => 'onHeaderLoaded',
        ];
    }

    public function onHeaderLoaded(HeaderPageletLoadedEvent $event): void
    {
        $config = $this->configProvider->toArray(
            $event->getSalesChannelContext()->getSalesChannelId(),
            $event->getSalesChannelContext()->getLanguageId(),
        );
        $nostoConfig = new Config($config);
        $event->getContext()->addExtension('nostoConfig', $nostoConfig);
    }
}
