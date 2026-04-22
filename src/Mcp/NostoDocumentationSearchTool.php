<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Mcp;

use Mcp\Capability\Registry\Loader\LoaderInterface;
use Mcp\Capability\RegistryInterface;
use Mcp\Schema\Tool;
use Mcp\Schema\ToolAnnotations;
use Nosto\NostoIntegration\Service\Mcp\NostoDocumentationMcpProxy;

final class NostoDocumentationSearchTool implements LoaderInterface
{
    private const TOOL_NAME = 'nosto-shopware6-documentation-search';

    public function __construct(
        private readonly NostoDocumentationMcpProxy $proxy,
    ) {
    }

    public function load(RegistryInterface $registry): void
    {
        $tool = new Tool(
            self::TOOL_NAME,
            [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'The documentation question to send to Nosto.',
                    ],
                    'scope' => [
                        'type' => 'string',
                        'enum' => ['technical', 'feature'],
                        'default' => 'technical',
                        'description' => 'Search technical docs or feature docs.',
                    ],
                ],
                'required' => ['query'],
            ],
            'Search Nosto documentation through the public Nosto MCP server.',
            new ToolAnnotations(
                title: 'Nosto documentation search',
                readOnlyHint: true,
                openWorldHint: true,
            ),
        );

        $registry->registerTool($tool, [$this, '__invoke'], true);
    }

    public function __invoke(string $query, string $scope = 'technical'): string
    {
        $query = trim($query);
        if ($query === '') {
            return $this->encodeResult($this->errorResult('The query argument is required.'));
        }

        $scope = strtolower(trim($scope));
        if (!\in_array($scope, ['technical', 'feature'], true)) {
            return $this->encodeResult($this->errorResult('The "scope" argument must be technical or feature.'));
        }

        $toolName = $scope === 'feature'
            ? 'get_nosto_feature_docs'
            : 'get_nosto_tech_docs';

        $result = $this->proxy->forwardDocumentationRequest([
            'tool' => $toolName,
            'arguments' => [
                'query' => $query,
            ],
        ]);

        return $this->encodeResult($result);
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

    /**
     * @param array<string, mixed> $result
     */
    private function encodeResult(array $result): string
    {
        try {
            return json_encode($result, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException('Could not encode the Nosto MCP tool response.', 0, $e);
        }
    }
}
