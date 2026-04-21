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

    private const MCP_CLIENT_NAME = 'nosto-shopware-docs-proxy';

    private const MCP_CLIENT_VERSION = '1.0.0';

    private const TECHNICAL_DOCS = 'technical-documentation-search';

    private const FEATURE_DOCS = 'feature-documentation-search';

    private int $jsonRpcRequestId = 0;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $mcpServerUrl,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function handleDocumentationRequest(array $requestPayload): array
    {
        try {
            $resolvedToolName = $this->resolveRequestedDocumentationTool($requestPayload['tool'] ?? null);
            $requestArguments = $requestPayload['arguments'] ?? [];

            if (!\is_array($requestArguments)) {
                return $this->errorResult('The "arguments" payload must be an object.');
            }

            $documentationQuery = $this->extractDocumentationQuery($requestArguments);
            if ($documentationQuery === null) {
                return $this->errorResult('The "query" argument is required.');
            }

            $mcpSessionId = $this->initializeMcpSession();
            $response = $this->callMcpTool($mcpSessionId, $resolvedToolName, $documentationQuery);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResult($e->getMessage());
        } catch (Throwable $e) {
            $this->logger->error('Failed to proxy the Nosto MCP documentation request.', [
                'exception' => $e,
                'tool' => $requestPayload['tool'] ?? null,
            ]);

            return $this->errorResult('Failed to fetch documentation from the Nosto MCP server.');
        }

        return $this->normalizeMcpResponse($response);
    }

    private function initializeMcpSession(): string
    {
        $response = $this->sendJsonRpcRequest('initialize', [
            'protocolVersion' => self::MCP_PROTOCOL_VERSION,
            'capabilities' => (object) [],
            'clientInfo' => [
                'name' => self::MCP_CLIENT_NAME,
                'version' => self::MCP_CLIENT_VERSION,
            ],
        ]);

        $mcpSessionId = $response['sessionId'] ?? null;
        if (!\is_string($mcpSessionId) || $mcpSessionId === '') {
            throw new \RuntimeException('The Nosto MCP server did not return a session id.');
        }

        return $mcpSessionId;
    }

    /**
     * @return array<string, mixed>
     */
    private function callMcpTool(string $mcpSessionId, string $toolName, string $query): array
    {
        return $this->sendJsonRpcRequest('tools/call', [
            'name' => $toolName,
            'arguments' => [
                'query' => $query,
            ],
        ], $mcpSessionId);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeMcpResponse(array $response): array
    {
        if (isset($response['result']) && \is_array($response['result'])) {
            return $response['result'];
        }

        if (isset($response['error']) && \is_array($response['error'])) {
            $message = $response['error']['message'] ?? 'The Nosto MCP server returned an error.';

            return $this->errorResult((string) $message);
        }

        return $this->errorResult('The Nosto MCP server returned an invalid or unexpected JSON-RPC response.');
    }

    /**
     * @return array<string, mixed>
     */
    private function sendJsonRpcRequest(string $method, array $params, ?string $mcpSessionId = null): array
    {
        $jsonRpcPayload = [
            'jsonrpc' => '2.0',
            'id' => ++$this->jsonRpcRequestId,
            'method' => $method,
            'params' => $params,
        ];

        try {
            $body = json_encode($jsonRpcPayload, \JSON_THROW_ON_ERROR);
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

        if ($mcpSessionId !== null) {
            $options['headers']['Mcp-Session-Id'] = $mcpSessionId;
        }

        try {
            $response = $this->httpClient->request('POST', $this->mcpServerUrl, $options);
            $statusCode = $response->getStatusCode();
            $responseBody = $response->getContent(false);
            $contentType = strtolower($response->getHeaders(false)['content-type'][0] ?? '');
        } catch (ExceptionInterface $e) {
            throw new \RuntimeException('Could not contact the Nosto MCP server: ' . $e->getMessage(), 0, $e);
        }

        if ($statusCode >= 400) {
            throw new \RuntimeException(\sprintf('The Nosto MCP server returned HTTP %d.', $statusCode));
        }

        $decoded = $this->decodeMcpResponseBody($responseBody, $contentType);
        if ($mcpSessionId === null) {
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
    private function decodeMcpResponseBody(string $responseBody, string $contentType): array
    {
        $responseBody = trim($responseBody);
        if ($responseBody === '') {
            return [];
        }

        if (str_contains($contentType, 'text/event-stream')) {
            $json = $this->extractLastEventData($responseBody);
            if ($json === null) {
                return [];
            }

            return $this->decodeJson($json);
        }

        return $this->decodeJson($responseBody);
    }

    private function extractLastEventData(string $responseBody): ?string
    {
        $json = null;
        foreach (preg_split("/\r?\n/", $responseBody) ?: [] as $line) {
            if (str_starts_with($line, 'data:')) {
                $json = ltrim(substr($line, 5));
            }
        }

        return $json;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $jsonPayload): array
    {
        try {
            $decoded = json_decode($jsonPayload, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException('The Nosto MCP server returned invalid JSON.', 0, $e);
        }

        if (!\is_array($decoded)) {
            throw new \RuntimeException('The Nosto MCP server returned an unexpected payload.');
        }

        return $decoded;
    }

    private function extractDocumentationQuery(array $requestArguments): ?string
    {
        $query = $requestArguments['query'] ?? $requestArguments['text'] ?? $requestArguments['search'] ?? null;

        if (!\is_string($query)) {
            return null;
        }

        $query = trim($query);

        return $query === '' ? null : $query;
    }

    private function resolveRequestedDocumentationTool(mixed $toolName): string
    {
        if (!\is_string($toolName) || trim($toolName) === '') {
            throw new \InvalidArgumentException('The "tool" field is required.');
        }

        $toolName = strtolower(trim($toolName));

        if (str_ends_with($toolName, self::FEATURE_DOCS)) {
            return self::FEATURE_DOCS;
        }

        if (str_ends_with($toolName, self::TECHNICAL_DOCS)) {
            return self::TECHNICAL_DOCS;
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
