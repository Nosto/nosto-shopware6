<?php declare(strict_types=1);

namespace Od\NostoIntegration\Model\MockOperation;

use Nosto\Operation\AbstractGraphQLOperation;
use Od\NostoIntegration\Model\MockOperation\Result\MockResultHandler;

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
