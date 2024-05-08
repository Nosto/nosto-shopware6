<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\MockOperation;

use Nosto\NostoIntegration\Model\MockOperation\Result\MockSearchResultHandler;
use Nosto\Operation\AbstractSearchOperation;
use Nosto\Types\Signup\AccountInterface;

class MockSearchOperation extends AbstractSearchOperation
{
    public function __construct(
        private readonly string $accountId,
        AccountInterface $account,
    ) {
        parent::__construct($account);
    }

    public function getQuery(): string
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

    /**
     * @return array<string, mixed>
     */
    public function getVariables(): array
    {
        return [
            'query' => '',
            'accountId' => $this->accountId,
        ];
    }

    protected function getResultHandler(): MockSearchResultHandler
    {
        return new MockSearchResultHandler();
    }
}
