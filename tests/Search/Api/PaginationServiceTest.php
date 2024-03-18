<?php

namespace Nosto\NostoIntegration\Tests\Search\Api;

use Nosto\NostoIntegration\Search\Api\PaginationService;
use Nosto\NostoIntegration\Tests\TestCase;
use Symfony\Component\HttpFoundation\Request;

class PaginationServiceTest extends TestCase
{
    private PaginationService $paginationService;

    public function setUp(): void
    {
        parent::setUp();

        $this->paginationService = new PaginationService();
    }

    public function requestProvider(): array
    {
        return [
            'no limit' => [
                'expectedOffset' => 48,
                'limit' => null,
                'method' => null,
                'parameters' => ['p' => '3'],
            ],
            'no page parameter' => [
                'expectedOffset' => 0,
                'limit' => 37,
                'method' => null,
                'request' => [],
            ],
            'negative page parameter' => [
                'expectedOffset' => 0,
                'limit' => 37,
                'method' => null,
                'request' => ['p' => '-5'],
            ],
            'page parameter POST' => [
                'expectedOffset' => 74,
                'limit' => 37,
                'method' => 'POST',
                'request' => ['p' => '3'],
            ],
            'page request GET' => [
                'expectedOffset' => 114,
                'limit' => 38,
                'method' => 'GET',
                'request' => ['p' => '4'],
            ]
        ];
    }

    /**
     * @dataProvider requestProvider
     */
    public function testGetRequestOffset(int $expectedOffset, ?int $limit, ?string $method, array $parameters)
    {
        $request = Request::create('https://localhost', $method ?? 'GET', $parameters);

        $this->assertSame(
            $expectedOffset,
            $this->paginationService->getRequestOffset($request, $limit),
        );
    }
}
