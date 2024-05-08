<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\MockOperation;

use Nosto\NostoIntegration\Model\MockOperation\Result\MockResultHandler;
use Nosto\Operation\AbstractGraphQLOperation;

class MockGraphQLOperation extends AbstractGraphQLOperation
{
    public function getQuery(): string
    {
        return '';
    }

    /**
     * @return array<string, mixed>
     */
    public function getVariables(): array
    {
        return [];
    }

    protected function getResultHandler(): MockResultHandler
    {
        return new MockResultHandler();
    }
}
