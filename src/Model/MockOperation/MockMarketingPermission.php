<?php declare(strict_types=1);

namespace Od\NostoIntegration\Model\MockOperation;

use Nosto\Operation\MarketingPermission;
use Nosto\Request\Api\Token;
use Od\NostoIntegration\Model\MockOperation\Result\MockResultHandler;

class MockMarketingPermission extends MarketingPermission
{
    public function mockUpdate()
    {
        $request = $this->initRequest(
            $this->account->getApiToken(Token::API_EMAIL),
            $this->account->getName(),
            $this->activeDomain
        );
        $request->setReplaceParams([]);
        $response = $request->postRaw('');

        return $request->getResultHandler()->parse($response);
    }

    protected function getResultHandler()
    {
        return new MockResultHandler();
    }
}
