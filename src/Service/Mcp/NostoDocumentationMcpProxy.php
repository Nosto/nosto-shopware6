<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Service\Mcp;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

final class NostoDocumentationMcpProxy
{
    private const NOSTO_MCP_PROTOCOL_VERSION = '2024-11-05';

    private const NOSTO_MCP_CLIENT_NAME = 'nosto-documentation';

    private const NOSTO_MCP_CLIENT_VERSION = '1.0.0';

    private const TECHNICAL_DOCUMENTATION = 'get_nosto_tech_docs';

    private const FEATURE_DOCUMENTATION = 'get_nosto_feature_docs';

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
    public function forwardDocumentationRequest(array $requestPayload): array
    {
        try {
            $resolvedToolName = $this->resolveRemoteDocumentationTool($requestPayload['tool'] ?? null);
            $requestArguments = $requestPayload['arguments'] ?? [];

            if (!\is_array($requestArguments)) {
                $this->logger->error('Arguments payload is not an array', ['type' => gettype($requestArguments)]);
                return $this->errorResult('The arguments payload must be an object.');
            }

            $documentationQuery = $this->extractDocumentationQuery($requestArguments);
            if ($documentationQuery === null) {
                $this->logger->error('No query argument found', ['arguments' => array_keys($requestArguments)]);
                return $this->errorResult('The query argument is required.');
            }

            $mcpSessionId = $this->initializeMcpSession();
            $this->logger->info('MCP session initialized', ['sessionId' => $mcpSessionId]);
            $this->sendInitializedNotification($mcpSessionId);
            $toolsList = $this->listRemoteTools($mcpSessionId);
            $response = $this->callRemoteDocumentationTool($mcpSessionId, $resolvedToolName, $documentationQuery);
        } catch (\InvalidArgumentException $e) {
            $this->logger->warning('Invalid argument in documentation request', [
                'exception' => $e->getMessage(),
            ]);

            return $this->errorResult($e->getMessage());
        } catch (Throwable $e) {
            $this->logger->error('Failed to proxy the Nosto MCP documentation request.', [
                'exception' => $e,
                'tool' => $requestPayload['tool'] ?? null,
                'mcpServerUrl' => $this->mcpServerUrl,
            ]);

            return $this->errorResult('Failed to fetch documentation from the Nosto MCP server.');
        }

        return $this->normalizeMcpResponse($response);
    }

    private function initializeMcpSession(): string
    {
        $response = $this->sendJsonRpcRequest('initialize', [
            'protocolVersion' => self::NOSTO_MCP_PROTOCOL_VERSION,
            'capabilities' => (object) [],
            'clientInfo' => [
                'name' => self::NOSTO_MCP_CLIENT_NAME,
                'version' => self::NOSTO_MCP_CLIENT_VERSION,
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
    private function callRemoteDocumentationTool(string $mcpSessionId, string $toolName, string $query): array
    {
        $toolCallPayload = [
            'name' => $toolName,
            'arguments' => [
                'query_input' => $query,
            ],
        ];

        return $this->sendJsonRpcRequest('tools/call', $toolCallPayload, $mcpSessionId);
    }

    private function sendInitializedNotification(string $mcpSessionId): void
    {
        $this->sendJsonRpcRequest('notifications/initialized', [], $mcpSessionId, true);
    }

    /**
     * @return array<string, mixed>
     */
    private function listRemoteTools(string $mcpSessionId): array
    {
        return $this->sendJsonRpcRequest('tools/list', [], $mcpSessionId);
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
    private function sendJsonRpcRequest(
        string $method,
        array $params,
        ?string $mcpSessionId = null,
        bool $isNotification = false,
    ): array {
        $jsonRpcPayload = [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => (object) $params,
        ];

        if (!$isNotification) {
            $jsonRpcPayload['id'] = ++$this->jsonRpcRequestId;
        }

        try {
            $body = json_encode($jsonRpcPayload, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->logger->error('Failed to encode JSON-RPC payload', ['error' => $e->getMessage()]);
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
        $buffer = '';
        $lastEventData = null;

        foreach (preg_split("/\r?\n/", $responseBody) ?: [] as $line) {
            if ($line === '') {
                if ($buffer !== '') {
                    $lastEventData = $buffer;
                    $buffer = '';
                }

                continue;
            }

            if (str_starts_with($line, 'data:')) {
                $buffer .= ($buffer === '' ? '' : "\n") . ltrim(substr($line, 5));
            }
        }

        if ($buffer !== '') {
            $lastEventData = $buffer;
        }

        return $lastEventData;
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

    private function resolveRemoteDocumentationTool(mixed $toolName): string
    {
        if (!\is_string($toolName) || trim($toolName) === '') {
            throw new \InvalidArgumentException('The tool field is required.');
        }

        $toolName = strtolower(trim($toolName));

        if ($toolName === self::FEATURE_DOCUMENTATION) {
            return self::FEATURE_DOCUMENTATION;
        }

        if ($toolName === self::TECHNICAL_DOCUMENTATION) {
            return self::TECHNICAL_DOCUMENTATION;
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
