<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\MockOperation\Result;

use Nosto\Request\Http\HttpResponse;
use Nosto\Result\Graphql\Recommendation\ResultSet;
use Nosto\Result\ResultHandler;

class MockSearchResultHandler extends ResultHandler
{
    public function parse(HttpResponse $response): ResultSet|bool|array|string
    {
        return $this->parseResponse($response);
    }

    protected function parseResponse(HttpResponse $response): array
    {
        if ($response->getCode() === 200) {
            $result = json_decode($response->getResult(), true);
            $errors = $result['errors'] ?? [];

            return [
                'success' => empty($errors),
                'message' => empty($errors) ? '' : current($errors)['message'],
            ];
        } else {
            return [
                'success' => false,
                'message' => $response->getMessage(),
            ];
        }
    }
}
