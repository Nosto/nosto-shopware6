<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search\Request\Handler;

use Monolog\Logger;
use Nosto\NostoIntegration\Enums\CategoryNamingOptions;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\Builder;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\Category\TreeBuilder;
use Nosto\NostoIntegration\Service\FilterPayloadService;
use Nosto\NostoIntegration\Utils\NostoCriteriaFactory;
use Nosto\Result\Graphql\Search\SearchResult;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class NavigationRequestHandler extends AbstractRequestHandler
{
    public function __construct(
        ConfigProvider $configProvider,
        SortingHandlerService $sortingHandlerService,
        Logger $logger,
        FilterPayloadService $filterPayloadService,
        private readonly EntityRepository $categoryRepository,
    ) {
        parent::__construct($configProvider, $sortingHandlerService, $logger, $filterPayloadService);
    }

    public function sendRequest(
        Request $request,
        Criteria $criteria,
        SalesChannelContext $context,
        ?int $limit = null,
    ): SearchResult {
        $searchOperation = $this->getSearchOperation($request, $criteria, $context, $limit);

        $searchOperation->setCategoryPath(
            $this->fetchCategoryPath($request->get('navigationId'), $context),
        );

        if ($searchOperation->getVariables()["query"] === "" && $searchOperation->getVariables()["sort"] === null) {
            $searchOperation->setQuery(null);
        }

        if ($this->configProvider->isEnabledProductVisibility(
            $context->getSalesChannelId(),
            $context->getLanguageId(),
        )) {
            $searchOperation->addValueFilter(
                'customFields.' . Builder::SHOW_CATEGORY,
                'true',
            );
        }

        $searchOperation->setResponseTimeout(3);
        $searchOperation->setConnectTimeout(3);
        $nostoResponse = $searchOperation->executeSw();

        if (empty($searchOperation->getVariables()['filter'])) {
            $data = json_decode($nostoResponse[0]);
            $data->data->search->products->hits = [];
            $request->attributes->set('nostoAPIResult', json_encode($data));
            $request->attributes->set('isInitialSearch', true);
        }
        $this->updateAbTestsCookie($request, $nostoResponse[1]->getAbTests());
        return $nostoResponse[1];
    }

    private function fetchCategoryPath(string $categoryId, SalesChannelContext $context): ?string
    {
        $criteria = NostoCriteriaFactory::createWithIds([$categoryId]);
        $criteria->addAssociation('seoUrls');

        /** @var ?CategoryEntity $category */
        $category = $this->categoryRepository
            ->search($criteria, $context->getContext())
            ->get($categoryId);

        if (!$category) {
            return null;
        }

        $withId = $this->configProvider->getCategoryNamingOption(
            $context->getSalesChannelId(),
            $context->getLanguageId(),
        );

        $categoryPath = '';
        foreach ($category->getSeoUrls()->getElements() as $seoUrl) {
            if ($seoUrl->getLanguageId() === $context->getLanguageId()
                && $seoUrl->getSalesChannelId() === $context->getSalesChannelId()
                && $seoUrl->getIsCanonical()
            ) {
                $categoryPath = '/' . rtrim($seoUrl->getSeoPathInfo(), '/');
                break;
            }
        }

        return $withId === CategoryNamingOptions::WITH_ID
            ? sprintf(
                TreeBuilder::NAME_WITH_ID_TEMPLATE,
                $categoryPath,
                $category->getId(),
            )
            : $categoryPath;
    }
}
