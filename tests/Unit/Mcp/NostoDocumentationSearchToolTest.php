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
    private const PUBLIC_MCP_URL = 'https://dev.mcp.nosto.com/mcp';

    public function testUsesTechnicalDocumentationByDefault(): void
    {
        $requests = [];
        $tool = new NostoDocumentationSearchTool($this->createProxy(
            'get_nosto_tech_docs',
            'Technical docs result.',
            'session-123',
            $requests,
        ));

        $result = json_decode($tool->__invoke('How do I expose docs MCP?'), true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame('Technical docs result.', $result['content'][0]['text']);
        self::assertFalse($result['isError']);
        self::assertCount(3, $requests);
    }

    public function testUsesFeatureDocumentationWhenRequested(): void
    {
        $requests = [];
        $tool = new NostoDocumentationSearchTool($this->createProxy(
            'get_nosto_feature_docs',
            'Feature docs result.',
            'session-456',
            $requests,
        ));

        $result = json_decode($tool->__invoke('How do I expose docs MCP?', 'feature'), true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame('Feature docs result.', $result['content'][0]['text']);
        self::assertFalse($result['isError']);
        self::assertCount(3, $requests);
    }

    /**
     * @param array<int, array{method: string, url: string, options: array<string, mixed>}> $requests
     */
    private function createProxy(
        string $expectedToolName,
        string $responseText,
        string $sessionId,
        array &$requests,
    ): NostoDocumentationMcpProxy {
        $client = new MockHttpClient(function (
            string $method,
            string $url,
            array $options,
        ) use (
            &$requests,
            $expectedToolName,
            $responseText,
            $sessionId,
        ): MockResponse {
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
                            'mcp-session-id' => $sessionId,
                            'content-type' => 'application/json',
                        ],
                    ],
                );
            }

            if (($payload['method'] ?? null) === 'notifications/initialized') {
                self::assertSame(
                    "Mcp-Session-Id: {$sessionId}",
                    $options['normalized_headers']['mcp-session-id'][0] ?? null,
                );

                return new MockResponse('', [
                    'http_code' => 200,
                    'response_headers' => [
                        'content-type' => 'application/json',
                    ],
                ]);
            }

            self::assertSame('tools/call', $payload['method'] ?? null);
            self::assertSame($expectedToolName, $payload['params']['name'] ?? null);
            self::assertSame('How do I expose docs MCP?', $payload['params']['arguments']['query_input'] ?? null);
            self::assertSame(
                "Mcp-Session-Id: {$sessionId}",
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
                                'text' => $responseText,
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

        return new NostoDocumentationMcpProxy($client, new NullLogger(), self::PUBLIC_MCP_URL);
    }
}
