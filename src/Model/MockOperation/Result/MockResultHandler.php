<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\MockOperation\Result;

use Nosto\Request\Http\HttpResponse;
use Nosto\Result\ResultHandler;

class MockResultHandler extends ResultHandler
{
    public function parse(HttpResponse $response)
    {
        return $this->parseResponse($response);
    }

    protected function parseResponse(HttpResponse $response)
    {
        if ($response->getCode() > 400) {
            $result = json_decode($response->getResult(), true);

            return [
                'success' => false,
                'message' => empty($result['message']) ? 'Unautorized' : $result['message'],
            ];
        }

        return [
            'success' => true,
        ];
    }
}
