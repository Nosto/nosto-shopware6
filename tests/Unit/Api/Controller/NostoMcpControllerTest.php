<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Api\Controller;

use Nosto\NostoIntegration\Api\Controller\NostoMcpController;
use Nosto\NostoIntegration\Service\Mcp\NostoDocumentationMcpProxy;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class NostoMcpControllerTest extends TestCase
{
    public function testDocumentationReturnsBadRequestForInvalidJson(): void
    {
        $controller = new NostoMcpController(
            new NostoDocumentationMcpProxy(new MockHttpClient(), new NullLogger()),
        );
        $request = Request::create(
            '/api/_action/nosto-docs-mcp/documentation',
            'POST',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
            ],
            '{invalid-json',
        );

        $response = $controller->documentation($request);

        self::assertSame(JsonResponse::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame(
            [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'The request body must be valid JSON.',
                    ],
                ],
                'isError' => true,
            ],
            json_decode($response->getContent() ?: '[]', true, 512, \JSON_THROW_ON_ERROR),
        );
    }

    public function testDocumentationDelegatesValidJsonToProxy(): void
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $payload = json_decode($options['body'] ?? '{}', true, 512, \JSON_THROW_ON_ERROR);

            if (($payload['method'] ?? null) === 'initialize') {
                return new MockResponse(
                    json_encode([
                        'jsonrpc' => '2.0',
                        'id' => $payload['id'] ?? 1,
                        'result' => [
                            'protocolVersion' => '2024-11-05',
                            'capabilities' => [],
                            'serverInfo' => [
                                'name' => 'nosto',
                                'version' => '1.0.0',
                            ],
                        ],
                    ], \JSON_THROW_ON_ERROR),
                    [
                        'http_code' => 200,
                        'response_headers' => [
                            'mcp-session-id' => 'session-123',
                            'content-type' => 'application/json',
                        ],
                    ],
                );
            }

            if (($payload['method'] ?? null) === 'tools/call') {
                return new MockResponse(
                    json_encode([
                        'jsonrpc' => '2.0',
                        'id' => $payload['id'] ?? 2,
                        'result' => [
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => 'Use the MCP endpoint and a proxy service.',
                                ],
                            ],
                            'isError' => false,
                        ],
                    ], \JSON_THROW_ON_ERROR),
                    [
                        'http_code' => 200,
                        'response_headers' => [
                            'content-type' => 'application/json',
                        ],
                    ],
                );
            }

            self::fail('Unexpected MCP request method: ' . ($payload['method'] ?? 'missing'));
        });

        $controller = new NostoMcpController(
            new NostoDocumentationMcpProxy($client, new NullLogger()),
        );
        $request = Request::create(
            '/api/_action/nosto-docs-mcp/documentation',
            'POST',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'tool' => 'nosto-docs-mcp-technical-documentation-search',
                'arguments' => [
                    'query' => 'How do I expose docs MCP?',
                ],
            ], \JSON_THROW_ON_ERROR),
        );

        $response = $controller->documentation($request);

        self::assertSame(JsonResponse::HTTP_OK, $response->getStatusCode());
        self::assertSame(
            [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Use the MCP endpoint and a proxy service.',
                    ],
                ],
                'isError' => false,
            ],
            json_decode($response->getContent() ?: '[]', true, 512, \JSON_THROW_ON_ERROR),
        );
    }
}
