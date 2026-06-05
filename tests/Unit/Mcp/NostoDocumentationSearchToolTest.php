<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Mcp;

use Nosto\NostoIntegration\Mcp\NostoDocumentationSearchTool;
use Nosto\NostoIntegration\Service\Mcp\NostoDocumentationMcpProxy;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class NostoDocumentationSearchToolTest extends TestCase
{
    public function testUsesTechnicalDocumentationByDefault(): void
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

            if (($payload['method'] ?? null) === 'notifications/initialized') {
                return new MockResponse('', [
                    'http_code' => 200,
                    'response_headers' => [
                        'content-type' => 'application/json',
                    ],
                ]);
            }

            if (($payload['method'] ?? null) === 'tools/list') {
                return new MockResponse(
                    json_encode([
                        'jsonrpc' => '2.0',
                        'id' => $payload['id'] ?? 2,
                        'result' => [
                            'tools' => [
                                [
                                    'name' => 'get_nosto_tech_docs',
                                ],
                                [
                                    'name' => 'get_nosto_feature_docs',
                                ],
                            ],
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

            self::assertSame('tools/call', $payload['method'] ?? null);
            self::assertSame('get_nosto_tech_docs', $payload['params']['name'] ?? null);
            self::assertSame('How do I expose docs MCP?', $payload['params']['arguments']['query_input'] ?? null);

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

        $tool = new NostoDocumentationSearchTool(
            new NostoDocumentationMcpProxy($client, new NullLogger(), 'https://dev.mcp.nosto.com/mcp'),
        );

        $result = $tool('How do I expose docs MCP?');

        self::assertSame('Use the MCP endpoint and a proxy service.', $result);
        self::assertCount(4, $requests);
    }

    public function testUsesFeatureDocumentationWhenRequested(): void
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
                            'mcp-session-id' => 'session-456',
                            'content-type' => 'application/json',
                        ],
                    ],
                );
            }

            if (($payload['method'] ?? null) === 'notifications/initialized') {
                return new MockResponse('', [
                    'http_code' => 200,
                    'response_headers' => [
                        'content-type' => 'application/json',
                    ],
                ]);
            }

            if (($payload['method'] ?? null) === 'tools/list') {
                return new MockResponse(
                    json_encode([
                        'jsonrpc' => '2.0',
                        'id' => $payload['id'] ?? 2,
                        'result' => [
                            'tools' => [
                                [
                                    'name' => 'get_nosto_tech_docs',
                                ],
                                [
                                    'name' => 'get_nosto_feature_docs',
                                ],
                            ],
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

            self::assertSame('tools/call', $payload['method'] ?? null);
            self::assertSame('get_nosto_feature_docs', $payload['params']['name'] ?? null);
            self::assertSame('How do I expose docs MCP?', $payload['params']['arguments']['query_input'] ?? null);

            return new MockResponse(
                json_encode([
                    'jsonrpc' => '2.0',
                    'id' => $payload['id'] ?? 3,
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

        $tool = new NostoDocumentationSearchTool(
            new NostoDocumentationMcpProxy($client, new NullLogger(), 'https://dev.mcp.nosto.com/mcp'),
        );

        $result = $tool('How do I expose docs MCP?', 'feature');

        self::assertSame('Feature docs result.', $result);
        self::assertCount(4, $requests);
    }
}
