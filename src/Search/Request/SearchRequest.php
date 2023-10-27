<?php

namespace Nosto\NostoIntegration\Search\Request;

use Nosto\Model\Signup\Account;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\Operation\AbstractSearchOperation;
use Nosto\Request\Api\Token;
use Nosto\Result\Graphql\Search\SearchResultHandler;

class SearchRequest extends AbstractSearchOperation
{
    public function __construct(
        private readonly ConfigProvider $configProvider
    ) {
        $account = new Account($this->configProvider->getAccountName());
        $account->addApiToken(new Token(Token::API_SEARCH, $this->configProvider->getSearchToken()));

        parent::__construct($account);
    }

    private string $query = '';

    private int $size = 20;

    private int $from = 0;

    private array $sort = [];

    private array $filters = [];

    public function setQuery(string $query): void
    {
        $this->query = $query;
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

    public function addValueFilter(string $filterName, string $value): void
    {
        if (array_key_exists($filterName, $this->filters)) {
            $this->filters[$filterName]['value'][] = $value;
        } else {
            $this->filters[$filterName] = [
                'field' => $filterName,
                'value' => [$value],
            ];
        }
    }

    public function addRangeFilter(string $filterName, ?string $min = null, ?string $max = null): void
    {
        $range = [];

        if (!is_null($min)) {
            $range['gt'] = $min;
        }
        if (!is_null($max)) {
            $range['lt'] = $max;
        }

        if (array_key_exists($filterName, $this->filters)) {
            $this->filters[$filterName]['range'] = array_merge(
                $this->filters[$filterName]['range'],
                $range
            );
        } else {
            $this->filters[$filterName] = [
                'field' => $filterName,
                'range' => $range,
            ];
        }
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
            'accountId' => $this->configProvider->getAccountId(),
            'query' => $this->query,
            'sort' => $this->sort,
            'size' => $this->size,
            'from' => $this->from,
            'filter' => array_values($this->filters),
            'sessionParams' => $this->sessionParams,
        ];
    }
}
