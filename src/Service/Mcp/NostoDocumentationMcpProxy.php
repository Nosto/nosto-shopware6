<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Service\Mcp;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

final class NostoDocumentationMcpProxy
{
    private const MCP_PROTOCOL_VERSION = '2024-11-05';

    private const CLIENT_NAME = 'nosto-shopware-docs-proxy';

    private const CLIENT_VERSION = '1.0.0';

    private const TECHNICAL_TOOL = 'technical-documentation-search';

    private const FEATURE_TOOL = 'feature-documentation-search';

    private int $requestId = 0;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $publicMcpUrl,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function forward(array $payload): array
    {
        try {
            $tool = $this->resolveLocalTool($payload['tool'] ?? null);
            $arguments = $payload['arguments'] ?? [];

            if (!\is_array($arguments)) {
                return $this->errorResult('The "arguments" payload must be an object.');
            }

            $query = $this->extractQuery($arguments);
            if ($query === null) {
                return $this->errorResult('The "query" argument is required.');
            }

            $sessionId = $this->initializeSession();
            $result = $this->callTool($sessionId, $tool, $query);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResult($e->getMessage());
        } catch (Throwable $e) {
            $this->logger->error('Failed to proxy the Nosto MCP documentation request.', [
                'exception' => $e,
                'tool' => $payload['tool'] ?? null,
            ]);

            return $this->errorResult('Failed to fetch documentation from the Nosto MCP server.');
        }

        return $this->extractResult($result);
    }

    private function initializeSession(): string
    {
        $response = $this->requestJsonRpc('initialize', [
            'protocolVersion' => self::MCP_PROTOCOL_VERSION,
            'capabilities' => (object) [],
            'clientInfo' => [
                'name' => self::CLIENT_NAME,
                'version' => self::CLIENT_VERSION,
            ],
        ]);

        $sessionId = $response['sessionId'] ?? null;
        if (!\is_string($sessionId) || $sessionId === '') {
            throw new \RuntimeException('The Nosto MCP server did not return a session id.');
        }

        return $sessionId;
    }

    /**
     * @return array<string, mixed>
     */
    private function callTool(string $sessionId, string $toolName, string $query): array
    {
        return $this->requestJsonRpc('tools/call', [
            'name' => $toolName,
            'arguments' => [
                'query' => $query,
            ],
        ], $sessionId);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractResult(array $result): array
    {
        if (isset($result['result']) && \is_array($result['result'])) {
            return $result['result'];
        }

        if (isset($result['error']) && \is_array($result['error'])) {
            $message = $result['error']['message'] ?? 'The Nosto MCP server returned an error.';

            return $this->errorResult((string) $message);
        }

        return $this->errorResult('The Nosto MCP server returned an invalid or unexpected JSON-RPC response.');
    }

    /**
     * @return array<string, mixed>
     */
    private function requestJsonRpc(string $method, array $params, ?string $sessionId = null): array
    {
        $payload = [
            'jsonrpc' => '2.0',
            'id' => ++$this->requestId,
            'method' => $method,
            'params' => $params,
        ];

        try {
            $body = json_encode($payload, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException('Could not encode the Nosto MCP request payload.', 0, $e);
        }

        $options = [
            'headers' => [
                'Accept' => 'application/json, text/event-stream',
                'Content-Type' => 'application/json',
            ],
            'body' => $body,
        ];

        if ($sessionId !== null) {
            $options['headers']['Mcp-Session-Id'] = $sessionId;
        }

        try {
            $response = $this->httpClient->request('POST', $this->publicMcpUrl, $options);
            $statusCode = $response->getStatusCode();
            $body = $response->getContent(false);
            $contentType = strtolower($response->getHeaders(false)['content-type'][0] ?? '');
        } catch (ExceptionInterface $e) {
            throw new \RuntimeException('Could not contact the Nosto MCP server: ' . $e->getMessage(), 0, $e);
        }

        if ($statusCode >= 400) {
            throw new \RuntimeException(\sprintf('The Nosto MCP server returned HTTP %d.', $statusCode));
        }

        $decoded = $this->decodeResponseBody($body, $contentType);
        if ($sessionId === null) {
            $responseSessionId = $response->getHeaders(false)['mcp-session-id'][0] ?? null;
            if (\is_string($responseSessionId) && $responseSessionId !== '') {
                $decoded['sessionId'] = $responseSessionId;
            }
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponseBody(string $body, string $contentType): array
    {
        $body = trim($body);
        if ($body === '') {
            return [];
        }

        if (str_contains($contentType, 'text/event-stream')) {
            $json = $this->extractLastEventData($body);
            if ($json === null) {
                return [];
            }

            return $this->decodeJson($json);
        }

        return $this->decodeJson($body);
    }

    private function extractLastEventData(string $body): ?string
    {
        $json = null;
        foreach (preg_split("/\r?\n/", $body) ?: [] as $line) {
            if (str_starts_with($line, 'data:')) {
                $json = ltrim(substr($line, 5));
            }
        }

        return $json;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $json): array
    {
        try {
            $decoded = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException('The Nosto MCP server returned invalid JSON.', 0, $e);
        }

        if (!\is_array($decoded)) {
            throw new \RuntimeException('The Nosto MCP server returned an unexpected payload.');
        }

        return $decoded;
    }

    private function extractQuery(array $arguments): ?string
    {
        $query = $arguments['query'] ?? $arguments['text'] ?? $arguments['search'] ?? null;

        if (!\is_string($query)) {
            return null;
        }

        $query = trim($query);

        return $query === '' ? null : $query;
    }

    private function resolveLocalTool(mixed $toolName): string
    {
        if (!\is_string($toolName) || trim($toolName) === '') {
            throw new \InvalidArgumentException('The "tool" field is required.');
        }

        $toolName = strtolower(trim($toolName));

        if (str_ends_with($toolName, self::FEATURE_TOOL)) {
            return self::FEATURE_TOOL;
        }

        if (str_ends_with($toolName, self::TECHNICAL_TOOL)) {
            return self::TECHNICAL_TOOL;
        }

        throw new \InvalidArgumentException('Unsupported documentation tool requested.');
    }

    /**
     * @return array<string, mixed>
     */
    private function errorResult(string $message): array
    {
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $message,
                ],
            ],
            'isError' => true,
        ];
    }
}
