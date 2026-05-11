<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Mcp;

use Mcp\Capability\Attribute\McpTool;
use Nosto\NostoIntegration\Service\Mcp\NostoDocumentationMcpProxy;

#[McpTool(
    name: 'nosto-shopware6-documentation-search',
    description: 'Search the Nosto documentation through the public MCP server.',
)]
final class NostoDocumentationSearchTool
{
    private const TECHNICAL_SCOPE = 'technical';

    private const FEATURE_SCOPE = 'feature';

    public function __construct(
        private readonly NostoDocumentationMcpProxy $proxy,
    ) {
    }

    public function __invoke(string $query, string $scope = self::TECHNICAL_SCOPE): string
    {
        $result = $this->proxy->forwardDocumentationRequest([
            'tool' => $scope === self::FEATURE_SCOPE ? 'get_nosto_feature_docs' : 'get_nosto_tech_docs',
            'arguments' => [
                'query' => $query,
            ],
        ]);

        if (($result['isError'] ?? false) === true) {
            return (string) ($result['content'][0]['text'] ?? 'Failed to fetch documentation from the Nosto MCP server.');
        }

        return (string) ($result['content'][0]['text'] ?? '');
    }
}
