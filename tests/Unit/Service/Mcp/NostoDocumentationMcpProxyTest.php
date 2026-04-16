<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Service\Mcp;

use Nosto\NostoIntegration\Service\Mcp\NostoDocumentationMcpProxy;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class NostoDocumentationMcpProxyTest extends TestCase
{
    public function testForwardsTechnicalDocumentationQueriesToTheRemoteMcpServer(): void
    {
        $requests = [];

        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests) {
            $requests[] = [
                'method' => $method,
                'url' => $url,
                'options' => $options,
            ];

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

            self::assertSame('tools/call', $payload['method'] ?? null);
            self::assertSame('technical-documentation-search', $payload['params']['name'] ?? null);
            self::assertSame('How do I expose docs MCP?', $payload['params']['arguments']['query'] ?? null);
            self::assertSame(
                'Mcp-Session-Id: session-123',
                $options['normalized_headers']['mcp-session-id'][0] ?? null,
            );

            return new MockResponse(
                json_encode([
                    'jsonrpc' => '2.0',
                    'id' => $payload['id'] ?? 3,
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
        });

        $proxy = new NostoDocumentationMcpProxy($client);
        $result = $proxy->forward([
            'tool' => 'nosto-docs-mcp-technical-documentation-search',
            'arguments' => [
                'query' => 'How do I expose docs MCP?',
            ],
        ]);

        self::assertSame('Use the MCP endpoint and a proxy service.', $result['content'][0]['text']);
        self::assertFalse($result['isError']);
        self::assertCount(2, $requests);
    }

    public function testForwardsFeatureDocumentationQueriesToTheRemoteMcpServer(): void
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options) {
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
                            'mcp-session-id' => 'session-456',
                            'content-type' => 'application/json',
                        ],
                    ],
                );
            }

            self::assertSame('tools/call', $payload['method'] ?? null);
            self::assertSame('feature-documentation-search', $payload['params']['name'] ?? null);
            self::assertSame('How do I expose docs MCP?', $payload['params']['arguments']['query'] ?? null);
            self::assertSame(
                'Mcp-Session-Id: session-456',
                $options['normalized_headers']['mcp-session-id'][0] ?? null,
            );

            return new MockResponse(
                json_encode([
                    'jsonrpc' => '2.0',
                    'id' => $payload['id'] ?? 2,
                    'result' => [
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'Feature docs result.',
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
        });

        $proxy = new NostoDocumentationMcpProxy($client);
        $result = $proxy->forward([
            'tool' => 'nosto-docs-mcp-feature-documentation-search',
            'arguments' => [
                'query' => 'How do I expose docs MCP?',
            ],
        ]);

        self::assertSame('Feature docs result.', $result['content'][0]['text']);
        self::assertFalse($result['isError']);
    }
}
