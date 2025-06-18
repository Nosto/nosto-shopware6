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
        $cookieValue = $request->cookies->get('nosto-search-session-params');

        if (!$cookieValue) {
            try {
                $client = new Client();
                // TODO: @ugljesa fix
                $nostoAccountId = 'pe5iajdk';

                $path = $request->getPathInfo();
                $isSearch = str_contains($path, '/search');
                $isCategory = $request->attributes->has('navigationId');

                $message = [
                    'url' => $request->getUri(),
                    'response_mode' => 'HTML',
                    'referrer' => $request->headers->get('referer'),
                    'page_type' => $isSearch ? 'search' : 'category',
                    'elements' => [],
                    'cart' => [],
                ];

                $events = [];

                foreach ($request->query->all() as $key => $value) {
                    if (empty($value)) {
                        continue;
                    }

                    $events[] = ['ec', $value];
                }
                $message['events'] = $events;


                $query = http_build_query([
                    'c' => $request->cookies->get('2c_cId') ?? '68514ad25e20f8f341712173e',
                    'm' => $nostoAccountId,
                    'message' => json_encode($message),
                    'skipEvents' => 'true',
                ]);

                $url = 'https://connect.nosto.com/ev1?' . $query;

                $response = $client->get($url, [
                    'headers' => [
                        'User-Agent' => $request->headers->get('User-Agent'),
                        'Accept' => 'application/json',
                        'Accept-Language' => $request->headers->get('Accept-Language'),
                        'Referer' => $request->headers->get('referer'),
                        'Cookie' => $request->headers->get('cookie'),
                    ]
                ]);
                $data = json_decode($response->getBody()->getContents(), true);

                $sessionParams = [
                    'segments' => array_map(
                        fn($s) => $s['id'],
                        $data['se']['active_segments'] ?? []
                    ),
                    'products' => [
                        'personalizationBoost' => array_map(
                            fn($cat) => [
                                'field' => 'affinities.categories',
                                'value' => [$cat['name']],
                                'weight' => $cat['score'],
                            ],
                            $data['af']['top_categories'] ?? []
                        ),
                    ],
                ];

                $searchOperation->setSessionParams($sessionParams);

            } catch (\Throwable $e) {
                $this->logger->warning('Nosto ev1 call failed: ' . $e->getMessage());
            }
        }

        if ($sessionParamsString = $request->cookies->get('nosto-search-session-params')) {
            $sessionParams = json_decode($sessionParamsString, true);
            $searchOperation->setSessionParams(empty($sessionParams) ? null : $sessionParams);
        }
    }
}
