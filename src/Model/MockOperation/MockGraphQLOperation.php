<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\MockOperation;

use Nosto\NostoIntegration\Model\MockOperation\Result\MockResultHandler;
use Nosto\Operation\AbstractGraphQLOperation;

class MockGraphQLOperation extends AbstractGraphQLOperation
{
    public function getQuery()
    {
        return '';
    }

    public function getVariables()
    {
        return [];
    }

    protected function getResultHandler()
    {
        return new MockResultHandler();
    }
}
