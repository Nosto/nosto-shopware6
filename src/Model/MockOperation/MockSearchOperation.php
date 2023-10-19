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
        {
            search(
                accountId: "$this->accountId",
                query:"",
                explain: true,
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
        return new stdClass();
    }

    protected function getResultHandler()
    {
        return new MockSearchResultHandler();
    }
}
