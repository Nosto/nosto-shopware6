<?php declare(strict_types=1);

namespace Od\NostoIntegration\Service\CategoryMerchandising;

use Nosto\Operation\AbstractGraphQLOperation;
use Nosto\Operation\Recommendation\{CategoryMerchandising, ExcludeFilters, IncludeFilters};
use Nosto\Result\Graphql\Recommendation\CategoryMerchandisingResult;
use Od\NostoIntegration\Model\ConfigProvider;
use Od\NostoIntegration\Service\CategoryMerchandising\Translator\{FilterTranslatorAggregate, ResultTranslator};
use Od\NostoIntegration\Utils\Logger\ContextHelper;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\{Criteria, EntitySearchResult};
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Throwable;

class MerchandisingSearchApi implements SalesChannelRepositoryInterface
{
    private SalesChannelRepositoryInterface $repository;
    private EntityRepositoryInterface $categoryRepository;
    private ResultTranslator $resultTranslator;
    private FilterTranslatorAggregate $filterTranslator;
    private SessionLookupResolver $resolver;
    private ConfigProvider $configProvider;
    private LoggerInterface $logger;

    public function __construct(
        SalesChannelRepositoryInterface $repository,
        EntityRepositoryInterface $categoryRepository,
        ResultTranslator $resultTranslator,
        FilterTranslatorAggregate $filterTranslator,
        SessionLookupResolver $resolver,
        ConfigProvider $configProvider,
        LoggerInterface $logger
    ) {
        $this->repository = $repository;
        $this->categoryRepository = $categoryRepository;
        $this->resultTranslator = $resultTranslator;
        $this->filterTranslator = $filterTranslator;
        $this->resolver = $resolver;
        $this->configProvider = $configProvider;
        $this->logger = $logger;
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
        $isMerchEnabled = $this->configProvider->isMerchEnabled($salesChannelContext->getSalesChannelId());
        try {
            $sessionId = $this->resolver->getSessionId();
        } catch (Throwable $throwable) {
            $sessionId = null;
            $this->logger->error(
                sprintf(
                    'Unable to load resolve session, reason: %s',
                    $throwable->getMessage()
                ),
                ContextHelper::createContextFromException($throwable)
            );
        }

        $account = $this->resolver->getNostoAccount($salesChannelContext->getSalesChannelId());

        if (!$isMerchEnabled || !$account || !$sessionId || $criteria->getLimit() == 0) {
            return $this->repository->searchIds($criteria, $salesChannelContext);
        }

        $category = $this->getCategoryName($criteria, $salesChannelContext);
        $includeFilters = !empty($criteria->getPostFilters())
            ? $this->filterTranslator->buildIncludeFilters($criteria->getPostFilters(), $salesChannelContext->getContext())
            : new IncludeFilters();

        try {
            $operation = new CategoryMerchandising(
                $account->getNostoAccount(),
                $sessionId,
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

            return new IdSearchResult(
                $result->getTotalPrimaryCount(),
                $this->resultTranslator->getProductIds($result),
                $criteria,
                $salesChannelContext->getContext()
            );
        } catch (\Exception $e) {
            $this->logger->error(
                $e->getMessage(),
                ContextHelper::createContextFromException($e)
            );
            return $this->repository->searchIds($criteria, $salesChannelContext);
        }
    }

    private function getCategoryName(Criteria $criteria, SalesChannelContext $context): string
    {
        $categoryId = $this->getCategoryId($criteria);
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $categoryId));
        $category = $this->categoryRepository->search($criteria, $context->getContext())->first();
        $plainCategoryBreadcrumbs = $category->getPlainBreadcrumb();
        $categoryBreadcrumbs = \array_slice($plainCategoryBreadcrumbs, 1);
        $categoryName = '';

        foreach ($categoryBreadcrumbs as $breadcrumb) {
            $categoryName .= '/' . $breadcrumb;
        }

        return $categoryName;
    }

    private function getCategoryId(Criteria $criteria): ?string
    {
        $categoryId = null;
        foreach ($criteria->getFilters() as $filter) {
            if ($filter instanceof EqualsFilter && $filter->getField() === 'product.categoriesRo.id') {
                $categoryId = $filter->getValue();
            }
        }

        return $categoryId;
    }
}