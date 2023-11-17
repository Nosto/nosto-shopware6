<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Subscriber;

use Nosto\NostoIntegration\Model\Config\NostoConfigService;
use Nosto\NostoIntegration\Struct\Config;
use Nosto\NostoIntegration\Struct\NostoService;
use Nosto\NostoIntegration\Struct\PageInformation;
use Nosto\NostoIntegration\Utils\SearchHelper;
use Psr\Cache\InvalidArgumentException;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FrontendSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly NostoConfigService $nostoConfigService,
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

    /**
     * @throws InvalidArgumentException
     */
    public function onHeaderLoaded(HeaderPageletLoadedEvent $event): void
    {
        $config = $this->nostoConfigService->getConfigWithInheritance(
            $event->getSalesChannelContext()->getSalesChannelId(),
            $event->getSalesChannelContext()->getLanguageId(),
        );
        $nostoConfig = new Config($config);
        $event->getContext()->addExtension('nostoConfig', $nostoConfig);

        $nostoService = new NostoService();
        $event->getContext()->addExtension('nostoService', $nostoService);

        $request = $event->getRequest();
        $isSearchPage = SearchHelper::isSearchPage($request);
        $isNavigationPage = SearchHelper::isNavigationPage($request);
        $pageInformation = new PageInformation($isSearchPage, $isNavigationPage);

        $event->getPagelet()->addExtension('nostoPageInformation', $pageInformation);
    }
}
