<?php declare(strict_types=1);

namespace Od\NostoIntegration\Service\CategoryMerchandising;

use Nosto\NostoException;
use Nosto\Operation\AbstractGraphQLOperation;
use Nosto\Operation\Recommendation\{CategoryMerchandising, ExcludeFilters, IncludeFilters};
use Nosto\Request\Http\Exception\{AbstractHttpException, HttpResponseException};
use Nosto\Result\Graphql\Recommendation\CategoryMerchandisingResult;
use Od\NostoIntegration\Service\CategoryMerchandising\Translator\{FilterTranslator, ResultTranslator};
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\{Criteria, EntitySearchResult};
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CategoryMerchandisingProvider implements SalesChannelRepositoryInterface
{
    private SalesChannelRepositoryInterface $repository;
    private EntityRepositoryInterface $categoryRepository;
    private ResultTranslator $resultTranslator;
    private FilterTranslator $filterTranslator;
    private SessionLookupResolver $resolver;

    public function __construct(
        SalesChannelRepositoryInterface $repository,
        EntityRepositoryInterface $categoryRepository,
        ResultTranslator $resultTranslator,
        FilterTranslator $filterTranslator,
        SessionLookupResolver $resolver
    ) {
        $this->repository = $repository;
        $this->categoryRepository = $categoryRepository;
        $this->resultTranslator = $resultTranslator;
        $this->filterTranslator = $filterTranslator;
        $this->resolver = $resolver;
    }

    public function search(Criteria $criteria, SalesChannelContext $salesChannelContext): EntitySearchResult
    {
        return $this->repository->search($criteria, $salesChannelContext);
    }

    public function aggregate(Criteria $criteria, SalesChannelContext $salesChannelContext): AggregationResultCollection
    {
        return $this->repository->aggregate($criteria, $salesChannelContext);
    }

    /**
     * @throws NostoException
     * @throws AbstractHttpException
     * @throws HttpResponseException
     */
    public function searchIds(Criteria $criteria, SalesChannelContext $salesChannelContext): IdSearchResult
    {
        $sessionData = $this->resolver->getSessionData($salesChannelContext);
        $customerId = $sessionData['customerId'];
        $account = $sessionData['account'];

        if (!$account || !$customerId || $criteria->getLimit() == 0) {
            return $this->repository->searchIds($criteria, $salesChannelContext);
        }

        $category = $this->getCategory($criteria, $salesChannelContext);
        $includeFilters = !empty($criteria->getPostFilters())
            ? $this->filterTranslator->setIncludeFilters($criteria->getPostFilters(), $salesChannelContext->getContext())
            : new IncludeFilters();

        try {
            $operation = new CategoryMerchandising(
                $account->getNostoAccount(),
                $customerId,
                $category,
                $criteria->getOffset() / $criteria->getLimit(),
                $includeFilters,
                new ExcludeFilters(),
                '',
                AbstractGraphQLOperation::IDENTIFIER_BY_CID,
                false,
                $criteria->getLimit(),
                ''
            );

            /** @var CategoryMerchandisingResult $result */
            $result = $operation->execute();

            if (!$result->getTotalPrimaryCount()) {
                throw new \Exception('There are no products from the Nosto.');
            }

            $productIds = $this->resultTranslator->getProductIds($result);

            return new IdSearchResult(
                $result->getTotalPrimaryCount(),
                $productIds,
                $criteria,
                $salesChannelContext->getContext());
        } catch (\Exception $e) {
            return $this->repository->searchIds($criteria, $salesChannelContext);
        }
    }

    private function getCategory(Criteria $criteria, SalesChannelContext $context): string
    {
        $categoryId = null;
        foreach ($criteria->getFilters() as $filter) {
            if ($filter instanceof EqualsFilter && $filter->getField() === 'product.categoriesRo.id') {
                $categoryId = $filter->getValue();
            }
        }
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('categoryId', $categoryId));
        $criteria->addFilter(new EqualsFilter('languageId', $context->getLanguageId()));
        $category = $this->categoryRepository->search($criteria, $context->getContext())->first()->getName();

        return '/' . $category;
    }
}
