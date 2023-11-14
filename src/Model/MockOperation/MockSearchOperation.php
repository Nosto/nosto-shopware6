<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\MockOperation;

use Nosto\NostoIntegration\Model\MockOperation\Result\MockSearchResultHandler;
use Nosto\Operation\AbstractSearchOperation;
use Nosto\Types\Signup\AccountInterface;
use stdClass;

class MockSearchOperation extends AbstractSearchOperation
{
    public function __construct(
        private readonly string $accountId,
        AccountInterface $account
    ) {
        parent::__construct($account);
    }

    public function getQuery()
    {
        return <<<GRAPHQL
        query(
            \$accountId: String,
            \$query: String,
        ) {
            search(
                accountId: \$accountId,
                query: \$query,
            ) {
                products {
                    total,
                }
            }
        }
        GRAPHQL;
    }

    public function getVariables()
    {
        return [
            'query' => '',
            'accountId' => $this->accountId,
        ];
    }

    protected function getResultHandler()
    {
        return new MockSearchResultHandler();
    }
}
