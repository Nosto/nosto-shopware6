<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search\Request\Handler;

use Monolog\Logger;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\Result\Graphql\Search\SearchResult;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class NavigationRequestHandler extends AbstractRequestHandler
{
    public function __construct(
        ConfigProvider $configProvider,
        SortingHandlerService $sortingHandlerService,
        Logger $logger,
        private readonly SalesChannelRepository $seoUrlRepository,
    ) {
        parent::__construct($configProvider, $sortingHandlerService, $logger);
    }

    public function sendRequest(
        Request $request,
        Criteria $criteria,
        SalesChannelContext $context,
        ?int $limit = null,
    ): SearchResult {
        $searchOperation = $this->getSearchOperation($request, $criteria, $context, $limit);

        $searchOperation->setCategoryPath(
            $this->fetchCategoryPath($request->getPathInfo(), $context),
        );

        return $searchOperation->execute();
    }

    private function fetchCategoryPath(string $pathInfo, SalesChannelContext $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('pathInfo', $pathInfo),
        );

        /** @var ?SeoUrlEntity $seoUrl */
        $seoUrl = $this->seoUrlRepository->search($criteria, $context)->first();
        if (!$seoUrl) {
            return $pathInfo;
        }

        return '/' . trim($seoUrl->getSeoPathInfo(), '/');
    }
}
