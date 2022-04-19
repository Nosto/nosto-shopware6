<?php declare(strict_types=1);

namespace Od\NostoIntegration\Service\CategoryMerchandising;

use Nosto\Operation\AbstractGraphQLOperation;
use Nosto\Operation\Recommendation\{CategoryMerchandising, ExcludeFilters, IncludeFilters};
use Nosto\Operation\Session\NewSession;
use Nosto\Result\Graphql\Recommendation\CategoryMerchandisingResult;
use Od\NostoIntegration\Model\Nosto\Account\Provider;
use Od\NostoIntegration\Service\CategoryMerchandising\Translator\{FilterTranslator, ResultTranslator};
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\{Criteria, EntitySearchResult};
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RequestStack;

class CategoryMerchandisingProvider implements SalesChannelRepositoryInterface
{
    private SalesChannelRepositoryInterface $repository;
    private Provider $accountProvider;
    private RequestStack $requestStack;
    private EntityRepositoryInterface $categoryRepository;
    private ResultTranslator $resultTranslator;
    private FilterTranslator $filterTranslator;

    public function __construct(
        SalesChannelRepositoryInterface $repository,
        Provider $accountProvider,
        RequestStack $requestStack,
        EntityRepositoryInterface $categoryRepository,
        ResultTranslator $resultTranslator,
        FilterTranslator $filterTranslator
    ) {
        $this->repository = $repository;
        $this->accountProvider = $accountProvider;
        $this->requestStack = $requestStack;
        $this->categoryRepository = $categoryRepository;
        $this->resultTranslator = $resultTranslator;
        $this->filterTranslator = $filterTranslator;
    }

    public function search(Criteria $criteria, SalesChannelContext $salesChannelContext): EntitySearchResult
    {
        return $this->repository->search($criteria, $salesChannelContext);
    }

    public function aggregate(Criteria $criteria, SalesChannelContext $salesChannelContext): AggregationResultCollection
    {
        return $this->repository->aggregate($criteria, $salesChannelContext);
    }

    public function searchIds(Criteria $criteria, SalesChannelContext $salesChannelContext): IdSearchResult
    {
        $request = $this->requestStack->getCurrentRequest();
        //TODO: move to separate service
        $customerId = $request->cookies->get('2c_cId');
        $account = $this->accountProvider->get($salesChannelContext->getSalesChannelId());
        //TODO: move to separate service and finish it
        if ($account && !$customerId) {
            $session = new NewSession($account->getNostoAccount(),'',false);
            $customerId = $session->execute();
            $request->cookies->set('2c_cId', $customerId);
        }
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
