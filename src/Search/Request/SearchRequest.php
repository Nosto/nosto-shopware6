<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search\Request;

use Nosto\Model\Signup\Account;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\Operation\AbstractSearchOperation;
use Nosto\Request\Api\Token;
use Nosto\Result\Graphql\Search\SearchResultHandler;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SearchRequest extends AbstractSearchOperation
{
    public function __construct(
        private readonly ConfigProvider $configProvider,
        private readonly SalesChannelContext $context,
    ) {
        $channelId = $this->context->getSalesChannelId();
        $languageId = $this->context->getLanguageId();

        $account = new Account($this->configProvider->getAccountName($channelId, $languageId));
        $account->addApiToken(
            new Token(Token::API_SEARCH, $this->configProvider->getSearchToken($channelId, $languageId)),
        );

        parent::__construct($account);
    }

    private string $query = '';

    private ?string $categoryId = null;

    private int $size = 20;

    private int $from = 0;

    private array $sort = [];

    private array $filters = [];

    private ?array $sessionParams = null;

    public function setQuery(string $query): void
    {
        $this->query = $query;
    }

    public function setCategoryId(string $categoryId): void
    {
        $this->categoryId = $categoryId;
    }

    public function setSort(string $field, string $order): void
    {
        $this->sort = [
            "field" => $field,
            "order" => strtolower($order),
        ];
    }

    public function setFrom(int $from): void
    {
        $this->from = $from;
    }

    public function setSize(int $size): void
    {
        $this->size = $size;
    }

    public function addValueFilter(string $filterField, string $value): void
    {
        if (array_key_exists($filterField, $this->filters)) {
            $this->filters[$filterField]['value'][] = $value;
        } else {
            $this->filters[$filterField] = [
                'field' => $filterField,
                'value' => [$value],
            ];
        }
    }

    public function addRangeFilter(string $filterField, ?string $min = null, ?string $max = null): void
    {
        $range = [];

        if (!is_null($min)) {
            $range['gt'] = $min;
        }
        if (!is_null($max)) {
            $range['lt'] = $max;
        }

        if (array_key_exists($filterField, $this->filters)) {
            $this->filters[$filterField]['range'] = array_merge(
                $this->filters[$filterField]['range'],
                $range
            );
        } else {
            $this->filters[$filterField] = [
                'field' => $filterField,
                'range' => $range,
            ];
        }
    }

    public function setSessionParams(array $sessionParams): void
    {
        $this->sessionParams = $sessionParams;
    }

    protected function getResultHandler()
    {
        return new SearchResultHandler();
    }

    public function getQuery()
    {
        return <<<GRAPHQL
        query(
            \$accountId: String,
            \$query: String,
            \$categoryId: String,
            \$sort: [InputSearchSort!],
            \$filter: [InputSearchTopLevelFilter!],
            \$sessionParams: InputSearchQuery,
            \$size: Int,
            \$from: Int,
        ) {
            search(
                accountId: \$accountId,
                query: \$query,
                products: {
                    categoryId: \$categoryId,
                    sort: \$sort,
                    filter: \$filter,
                    size: \$size,
                    from: \$from,
                },
                sessionParams: \$sessionParams,
            ) {
                redirect,
                products {
                    total,
                    hits {
                        productId
                    }
                    facets {
                        ... on SearchStatsFacet {
                            id,
                            name
                            field,
                            type,
                            min,
                            max
                        }
                        ... on SearchTermsFacet {
                            id,
                            name
                            field,
                            type,
                            data {
                                value,
                                count,
                                selected,
                            }
                        }
                    }
                }
            }
        }
        GRAPHQL;
    }

    public function getVariables(): array
    {
        return [
            'accountId' => $this->configProvider->getAccountId(
                $this->context->getSalesChannelId(),
                $this->context->getLanguageId(),
            ),
            'query' => $this->query,
            'categoryId' => $this->categoryId,
            'sort' => $this->sort,
            'size' => $this->size,
            'from' => $this->from,
            'filter' => array_values($this->filters),
            'sessionParams' => $this->sessionParams,
        ];
    }
}
