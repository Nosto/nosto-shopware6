<?php

namespace Nosto\NostoIntegration\Core\Content\Product\SalesChannel\Listing\Processor;

use Nosto\NostoIntegration\Search\Api\SearchService;
use Shopware\Core\Content\Product\SalesChannel\Listing\Processor\AbstractListingProcessor;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class NostoListingProcessor extends AbstractListingProcessor
{
    public function __construct(
        private readonly SearchService $searchService,
    ) {
    }

    public function getDecorated(): AbstractListingProcessor
    {
        throw new DecorationPatternException(self::class);
    }

    public function prepare(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        $this->searchService->doSearch($request, $criteria);
    }
}
