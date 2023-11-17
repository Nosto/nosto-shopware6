<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\MockOperation;

use Nosto\NostoIntegration\Model\MockOperation\Result\MockResultHandler;
use Nosto\Operation\MarketingPermission;
use Nosto\Request\Api\Token;
use Nosto\Result\Graphql\Recommendation\ResultSet;

class MockMarketingPermission extends MarketingPermission
{
    public function mockUpdate(): string|array|bool|ResultSet
    {
        $request = $this->initRequest(
            $this->account->getApiToken(Token::API_EMAIL),
            $this->account->getName(),
            $this->activeDomain,
        );
        $request->setReplaceParams([]);
        $response = $request->postRaw('');

        return $request->getResultHandler()->parse($response);
    }

    protected function getResultHandler(): MockResultHandler
    {
        return new MockResultHandler();
    }
}
