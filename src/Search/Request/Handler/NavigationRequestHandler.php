<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search\Request\Handler;

use Monolog\Logger;
use Nosto\NostoIntegration\Enums\CategoryNamingOptions;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\Category\TreeBuilder;
use Nosto\Result\Graphql\Search\SearchResult;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class NavigationRequestHandler extends AbstractRequestHandler
{
    public function __construct(
        ConfigProvider $configProvider,
        SortingHandlerService $sortingHandlerService,
        Logger $logger,
        private readonly SalesChannelRepository $categoryRepository,
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
            $this->fetchCategoryPath($request->get('navigationId'), $context),
        );

        if ($searchOperation->getVariables()["query"] === "" && $searchOperation->getVariables()["sort"] === null) {
            $searchOperation->setQuery(null);
        }
        $searchOperation->setResponseTimeout(3);
        $searchOperation->setConnectTimeout(3);

        return $searchOperation->execute();
    }

    private function fetchCategoryPath(string $categoryId, SalesChannelContext $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addAssociation('seoUrls');
        $criteria->setIds([$categoryId]);

        /** @var ?CategoryEntity $category */
        $category = $this->categoryRepository
            ->search($criteria, $context)
            ->first();

        if (!$category) {
            return null;
        }

        $withId = $this->configProvider->getCategoryNamingOption(
            $context->getSalesChannelId(),
            $context->getLanguageId(),
        );

        if ($category->getSeoUrls()->first()) {
            $categoryPath = '/' . rtrim($category->getSeoUrls()->first()->seoPathInfo, '/');

             return $withId === CategoryNamingOptions::WITH_ID
                ? sprintf(
                    TreeBuilder::NAME_WITH_ID_TEMPLATE,
                    $categoryPath,
                    $category->getId(),
                )
                : $categoryPath;
        } else {
            $pathIds = explode('|', trim($category->getPath(), '|'));
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('parentId', null));
            $criteria->setIds([$pathIds[0]]);

            $rootCategory = $this->categoryRepository
                ->search($criteria, $context)
                ->first();

            $rootCategoryNames[] = $rootCategory->getTranslation('name');

            $mapping = $category->getTranslation('breadcrumb');
            $categoryName = $category->getTranslation('name');
            $navigationCategoryId = $context->getSalesChannel()->getNavigationCategoryId();

            $categoryNames = array_map(
                static fn (string $categoryId): string => $mapping[$categoryId],
                array_filter($pathIds, static fn (string $id): bool => $id !== $navigationCategoryId),
            );

            $categoryNames = array_diff($categoryNames, $rootCategoryNames);

            $categoryNames[] = $withId === CategoryNamingOptions::WITH_ID
                ? sprintf(
                    TreeBuilder::NAME_WITH_ID_TEMPLATE,
                    $categoryName,
                    $category->getId(),
                )
                : $categoryName;

            return '/' . implode('/', $categoryNames);
        }
    }
}
