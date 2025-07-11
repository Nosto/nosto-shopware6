<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search\Request\Handler;

use GuzzleHttp\Client;
use Monolog\Logger;
use Nosto\Model\Signup\Account;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Search\Response\GraphQL\GraphQLResponseParser;
use Nosto\NostoIntegration\Struct\Redirect;
use Nosto\Operation\Search\SearchOperation;
use Nosto\Request\Api\Token;
use Nosto\Result\Graphql\Search\SearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
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

        if ($responseParser->getProductIds()) {
            $criteria->setIds($responseParser->getProductIds());
        }

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
        $this->setSessionParamsFromCookies($request, $searchOperation, $channelId, $languageId);
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

    protected function setSessionParamsFromCookies(
        Request $request,
        SearchOperation $searchOperation,
        $channelId,
        $languageId,
    ): void {
        $cookieName = 'nosto-search-session-params';
        $cookieValue = $request->cookies->get($cookieName);

        if (!$cookieValue) {
            try {
                $nostoAccountId = (new Account(
                    $this->configProvider->getAccountId($channelId, $languageId),
                ))->getName();

                $isSearch = str_contains($request->getPathInfo(), '/search');
                $isCategory = $request->attributes->has('navigationId');

                $message = [
                    'url' => $request->getUri(),
                    'response_mode' => 'HTML',
                    'referrer' => $request->headers->get('referer'),
                    'page_type' => $isSearch ? 'search' : ($isCategory ? 'category' : 'other'),
                    'elements' => [],
                    'cart' => [],
                    'events' => [],
                ];

                foreach ($request->query->all() as $value) {
                    if (!empty($value)) {
                        $message['events'][] = ['ec', $value];
                    }
                }

                $clientId = $request->cookies->get('2c_cId') ?? Uuid::randomHex();

                $queryParams = [
                    'c' => $clientId,
                    'm' => $nostoAccountId,
                    'message' => json_encode($message),
                    'skipEvents' => 'true',
                ];

                $response = (new Client())->get('https://connect.nosto.com/ev1?' . http_build_query($queryParams), [
                    'headers' => [
                        'User-Agent' => $request->headers->get('User-Agent'),
                        'Accept' => 'application/json',
                        'Accept-Language' => $request->headers->get('Accept-Language'),
                        'Referer' => $request->headers->get('referer'),
                        'Cookie' => $request->headers->get('cookie'),
                    ],
                ]);

                $responseData = json_decode($response->getBody()->getContents(), true);

                $segments = array_column($responseData['se']['active_segments'] ?? [], 'id');

                $categories = array_map(
                    fn ($cat) => [
                        'field' => 'affinities.categories',
                        'value' => [$cat['name']],
                        'weight' => $cat['score'],
                    ],
                    $responseData['af']['top_categories'] ?? [],
                );

                $sessionParams = [
                    'segments' => $segments,
                    'products' => [
                        'personalizationBoost' => $categories,
                    ],
                ];

                $searchOperation->setSessionParams($sessionParams);
            } catch (\Throwable $e) {
                $this->logger->error('Nosto ev1 call failed: ' . $e->getMessage());
            }
        }

        if ($cookieValue = $request->cookies->get($cookieName)) {
            $sessionParams = json_decode($cookieValue, true);
            $searchOperation->setSessionParams(!empty($sessionParams) ? $sessionParams : null);
        }
    }
}
