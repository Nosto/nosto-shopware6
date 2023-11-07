<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Core\Content\Product\SalesChannel\Listing\Processor;

use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Search\Api\SearchService;
use Nosto\NostoIntegration\Utils\SearchHelper;
use Shopware\Core\Content\Product\SalesChannel\Listing\Processor\AbstractListingProcessor;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class NostoListingProcessor extends AbstractListingProcessor
{
    public function __construct(
        private readonly SearchService $searchService,
        private readonly ConfigProvider $configProvider,
    ) {
    }

    public function getDecorated(): AbstractListingProcessor
    {
        throw new DecorationPatternException(self::class);
    }

    public function prepare(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        if (SearchHelper::shouldHandleRequest(
            $context->getContext(),
            $this->configProvider,
            SearchHelper::isNavigationPage($request)
        )) {
            if (SearchHelper::isSearchPage($request)) {
                $this->searchService->doSearch($request, $criteria, $context);
            } elseif (SearchHelper::isNavigationPage($request)) {
                $this->searchService->doNavigation($request, $criteria, $context);
            }
        }
    }
}
