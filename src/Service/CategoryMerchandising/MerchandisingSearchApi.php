<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Service\CategoryMerchandising;

use Exception;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Service\CategoryMerchandising\Translator\{FilterTranslatorAggregate, ResultTranslator};
use Nosto\NostoIntegration\Utils\Logger\ContextHelper;
use Nosto\Operation\AbstractGraphQLOperation;
use Nosto\Operation\Recommendation\CategoryMerchandising;
use Nosto\Operation\Recommendation\ExcludeFilters;
use Nosto\Operation\Recommendation\IncludeFilters;
use Nosto\Result\Graphql\Recommendation\CategoryMerchandisingResult;
use Nosto\Result\Graphql\Recommendation\ResultSet;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RequestStack;
use Throwable;

class MerchandisingSearchApi extends SalesChannelRepository
{
    public const MERCHANDISING_SORTING_KEY = 'nosto-recommendation';

    private SalesChannelRepository $repository;

    private EntityRepository $categoryRepository;

    private ResultTranslator $resultTranslator;

    private FilterTranslatorAggregate $filterTranslator;

    private SessionLookupResolver $resolver;

    private ConfigProvider $configProvider;

    private RequestStack $requestStack;

    private LoggerInterface $logger;

    public function __construct(
        SalesChannelRepository $repository,
        EntityRepository $categoryRepository,
        ResultTranslator $resultTranslator,
        FilterTranslatorAggregate $filterTranslator,
        SessionLookupResolver $resolver,
        ConfigProvider $configProvider,
        RequestStack $requestStack,
        LoggerInterface $logger
    ) {
        $this->repository = $repository;
        $this->categoryRepository = $categoryRepository;
        $this->resultTranslator = $resultTranslator;
        $this->filterTranslator = $filterTranslator;
        $this->resolver = $resolver;
        $this->configProvider = $configProvider;
        $this->requestStack = $requestStack;
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
            $sessionId = $this->resolver->getSessionId($salesChannelContext->getContext());
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

        $account = $this->resolver->getNostoAccount(
            $salesChannelContext->getContext(),
            $salesChannelContext->getSalesChannelId()
        );

        if (
            !$isMerchEnabled ||
            $this->getSortingKey() !== self::MERCHANDISING_SORTING_KEY ||
            !$account ||
            !$sessionId ||
            $criteria->getLimit() == 0
        ) {
            return $this->repository->searchIds($criteria, $salesChannelContext);
        }

        $categoryName = $this->getCategoryName($criteria, $salesChannelContext);

        if (!$categoryName) {
            $requestUri = $this->requestStack->getCurrentRequest()->getRequestUri();

            if ($requestUri) {
                $categoryIdArr = explode('/', $requestUri);
                $categoryId = array_pop($categoryIdArr);

                $category = $this->categoryRepository->search(
                    new Criteria([$categoryId]),
                    $salesChannelContext->getContext()
                )->first();

                $categoryName = $this->getCategoryNameByBreadcrumbs($category->getPlainBreadcrumb());
            }
        }

        $includeFilters = !empty($criteria->getPostFilters())
            ? $this->filterTranslator->buildIncludeFilters(
                $criteria->getPostFilters(),
                $salesChannelContext->getContext()
            )
            : new IncludeFilters();

        try {
            $operation = new CategoryMerchandising(
                $account->getNostoAccount(),
                $sessionId,
                $categoryName,
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

            if ($this->configProvider->getProductIdentifier($salesChannelContext->getSalesChannelId()) === 'product-number') {
                $result = $this->replaceSkusWithActualIds($result, $salesChannelContext);
            }

            return new IdSearchResult(
                $result->getTotalPrimaryCount(),
                $this->resultTranslator->getProductIds($result),
                $criteria,
                $salesChannelContext->getContext()
            );
        } catch (Exception $e) {
            $this->logger->error(
                $e->getMessage(),
                ContextHelper::createContextFromException($e)
            );

            return $this->repository->searchIds($criteria, $salesChannelContext);
        }
    }

    /**
     * Searches for any sku's in shopware that are marked as productIdentifiers in Nosto catalog.
     * Upon finding skus like that then replace the identifier (product sku as id) with actual
     * productId (product id as id) that is used in Shopware.
     *
     * @throws ReflectionException
     */
    private function replaceSkusWithActualIds($result, $context): CategoryMerchandisingResult
    {
        $productIdentifiers = [];

        foreach ($result->getResultSet() as $productFromNostoCatalog) {
            $productIdentifiers[] = $productFromNostoCatalog->getProductId();
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('product.productNumber', $productIdentifiers));

        // Search Product catalog in Shopware. If any products are found here that means that
        // their ( products ) identifier is an actual sku
        $products = $this->repository->search($criteria, $context);

        // Just a sorting/mapping for easier access later on.
        $replaceSkusWithIds = [];

        foreach ($products as $product) {
            $replaceSkusWithIds[$product->getId()] = $product->getProductNumber();
        }

        $newResultSet = new ResultSet();

        // Search and replace sku with the product id
        // Reflection seems to me the cleanest way to achieve this
        foreach ($result->getResultSet() as $productToChangeData) {
            if ($key = array_search($productToChangeData->getProductId(), $replaceSkusWithIds)) {
                $itemReflection = new ReflectionClass($productToChangeData);
                $secret = $itemReflection->getProperty('data');
                $secret->setAccessible(true);
                $newData = $secret->getValue($productToChangeData);
                $newData['productId'] = $key;
                $secret->setValue($productToChangeData, $newData);
            }

            try {
                // Check if uuid doesn't cause a crash. This is mostly to prevent a page crash in edge cases.
                Uuid::fromHexToBytes($productToChangeData->getProductId());
                $newResultSet->append($productToChangeData);
            } catch (Exception $e) {
                // Nothing. Just skip.
            }
        }

        // Return the same result but with skus of products replaced with product ids.
        return new CategoryMerchandisingResult(
            $newResultSet,
            $result->getTrackingCode(),
            $result->getTotalPrimaryCount(),
            $result->getBatchToken()
        );
    }

    private function getSortingKey(): string
    {
        $request = $this->requestStack->getCurrentRequest();

        return $request ? $request->get('order', '') : '';
    }

    private function getCategoryName(Criteria $criteria, SalesChannelContext $context): string
    {
        $categoryId = $this->getCategoryId($criteria);
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $categoryId));
        $category = $this->categoryRepository->search($criteria, $context->getContext())->first();

        if ($category === null) {
            return '';
        }

        return $this->getCategoryNameByBreadcrumbs($category->getPlainBreadcrumb());
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

    private function getCategoryNameByBreadcrumbs($categoryBreadcrumbs): string
    {
        $breadcrumbs = \array_slice($categoryBreadcrumbs, 1);
        $categoryFullName = '';

        foreach ($breadcrumbs as $breadcrumb) {
            $categoryFullName .= '/' . $breadcrumb;
        }

        return $categoryFullName;
    }
}
