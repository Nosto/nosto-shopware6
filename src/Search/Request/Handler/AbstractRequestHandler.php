<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search\Request\Handler;

use Monolog\Logger;
use Nosto\Model\Signup\Account;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Search\Response\GraphQL\GraphQLResponseParser;
use Nosto\NostoIntegration\Struct\Redirect;
use Nosto\Operation\Search\SearchOperation;
use Nosto\Request\Api\Token;
use Nosto\Result\Graphql\Search\SearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

abstract class AbstractRequestHandler
{
    protected readonly FilterHandler $filterHandler;

    public function __construct(
        protected readonly ConfigProvider $configProvider,
        protected readonly SortingHandlerService $sortingHandlerService,
        protected readonly Logger $logger,
    ) {
        $this->filterHandler = new FilterHandler();
    }

    /**
     * Sends a request to the Nosto service based on the given event and the responsible request handler.
     *
     * @param int|null $limit limited amount of products
     */
    abstract public function sendRequest(
        Request $request,
        Criteria $criteria,
        SalesChannelContext $context,
        ?int $limit = null,
    ): SearchResult;

    public function fetchProducts(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        $originalCriteria = clone $criteria;

        try {
            $response = $this->sendRequest($request, $criteria, $context);
            $responseParser = new GraphQLResponseParser($response);
        } catch (Throwable $e) {
            $this->logger->error(
                sprintf('Error while fetching the products: %s', $e->getMessage()),
            );
            return;
        }

        if ($redirect = $responseParser->getRedirectExtension()) {
            $this->handleRedirect($context, $redirect);

            return;
        }

        $productIds = [];
        foreach($responseParser->getProductCustomFields() as $customFields) {
            foreach($customFields as $customField) {
                if ($customField->getKey() === 'productid') {
                    $productIds[$customField->getValue()] = $customField->getValue();
                    break;
                }
            }
        }

        $criteria->setIds($productIds);

        $this->setPagination(
            $criteria,
            $responseParser,
            $originalCriteria->getLimit(),
            $originalCriteria->getOffset(),
        );
    }

    protected function handleRedirect(SalesChannelContext $context, Redirect $redirectExtension): void
    {
        $context->getContext()->addExtension(
            'nostoRedirect',
            $redirectExtension,
        );
    }

    protected function getSearchOperation(
        Request $request,
        Criteria $criteria,
        SalesChannelContext $context,
        ?int $limit = null,
    ): SearchOperation {
        $channelId = $context->getSalesChannelId();
        $languageId = $context->getLanguageId();
        $searchOperation = new SearchOperation($this->getAccount($channelId, $languageId));

        $searchOperation->setAccountId($this->configProvider->getAccountId($channelId, $languageId));
        $this->setPaginationParams($criteria, $searchOperation, $limit);
        $this->setSessionParamsFromCookies($request, $searchOperation);
        $this->sortingHandlerService->handle($searchOperation, $criteria);
        if ($criteria->hasExtension('nostoFilters')) {
            $this->filterHandler->handleFilters($request, $criteria, $searchOperation);
        }

        return $searchOperation;
    }

    protected function getAccount(string $salesChannelId, string $languageId): Account
    {
        $account = new Account($this->configProvider->getAccountName($salesChannelId, $languageId));
        $account->addApiToken(
            new Token(Token::API_SEARCH, $this->configProvider->getSearchToken($salesChannelId, $languageId)),
        );

        return $account;
    }

    protected function setPaginationParams(
        Criteria $criteria,
        SearchOperation $searchOperation,
        ?int $limit,
    ): void {
        $searchOperation->setFrom($criteria->getOffset() ?? 0);
        $searchOperation->setSize($limit ?? $criteria->getLimit());
    }

    protected function setPagination(
        Criteria $criteria,
        GraphQLResponseParser $responseParser,
        ?int $limit,
        ?int $offset,
    ): void {
        $pagination = $responseParser->getPaginationExtension($limit, $offset);
        $criteria->addExtension('nostoPagination', $pagination);
    }

    protected function setSessionParamsFromCookies(Request $request, SearchOperation $searchOperation): void
    {
        if ($sessionParamsString = $request->cookies->get('nosto-search-session-params')) {
            $sessionParams = json_decode($sessionParamsString, true);
            $searchOperation->setSessionParams($sessionParams);
        }
    }
}
