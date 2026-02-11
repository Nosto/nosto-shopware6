<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\MockOperation;

use Nosto\Model\ExchangeRateCollection;
use Nosto\NostoIntegration\Model\MockOperation\Result\MockResultHandler;
use Nosto\Operation\SyncRates;
use Nosto\Request\Http\Exception\AbstractHttpException;
use Nosto\Request\Api\Token;
use Nosto\Result\Graphql\Recommendation\ResultSet;
use Nosto\NostoException;

class MockExchangeRates extends SyncRates
{
    public function mockUpdate(): ResultSet|bool|array|string
    {
        $request = $this->initRequest(
            $this->account->getApiToken(Token::API_EXCHANGE_RATES),
            $this->account->getName(),
            $this->activeDomain,
        );
        $request->setReplaceParams([]);
        $response = $request->postRaw('');

        return $this->getResultHandler()->parse($response);
    }

    protected function getResultHandler(): MockResultHandler
    {
        return new MockResultHandler();
    }
}
