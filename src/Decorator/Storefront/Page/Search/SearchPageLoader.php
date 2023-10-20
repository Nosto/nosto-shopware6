<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Decorator\Storefront\Page\Search;

use Nosto\NostoIntegration\Model\ConfigProvider;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Product\SalesChannel\Search\AbstractProductSearchRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoader;
use Shopware\Storefront\Page\Search\SearchPage;
use Shopware\Storefront\Page\Search\SearchPageLoadedEvent;
use Shopware\Storefront\Page\Search\SearchPageLoader as ShopwareSearchPageLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @see ShopwareSearchPageLoader
 */
class SearchPageLoader extends ShopwareSearchPageLoader
{
    public function __construct(
        private readonly ShopwareSearchPageLoader $decorated,
        private readonly GenericPageLoader $genericLoader,
        private readonly ?AbstractProductSearchRoute $productSearchRoute,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ConfigProvider $configProvider,
    ) {
    }

    /**
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): SearchPage
    {
        if (!$this->configProvider->isSearchEnabled()) {
            return $this->decorated->load($request, $salesChannelContext);
        }

        $page = $this->genericLoader->load($request, $salesChannelContext);
        $page = SearchPage::createFrom($page);

        if ($page->getMetaInformation()) {
            $page->getMetaInformation()->setRobots('noindex,follow');
        }

        $criteria = new Criteria();
        $criteria->setTitle('search-page');

        $result = $this->productSearchRoute
            ->load($request, $salesChannelContext, $criteria)
            ->getListingResult();

        $page->setListing($result);

        $page->setSearchTerm(
            (string) $request->query->get('search')
        );

        $this->eventDispatcher->dispatch(
            new SearchPageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }
}
