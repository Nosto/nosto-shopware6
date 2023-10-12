<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\MockOperation;

use Nosto\Model\Product\ProductCollection;
use Nosto\NostoIntegration\Model\MockOperation\Result\MockResultHandler;
use Nosto\Operation\UpsertProduct;
use Nosto\Request\Api\Token;

class MockUpsertProduct extends UpsertProduct
{
    public function upsert()
    {
        $request = $this->initRequest(
            $this->account->getApiToken(Token::API_PRODUCTS),
            $this->account->getName(),
            $this->activeDomain
        );
        $response = $request->post(new ProductCollection());

        return $this->getResultHandler()->parse($response);
    }

    protected function getResultHandler()
    {
        return new MockResultHandler();
    }
}
