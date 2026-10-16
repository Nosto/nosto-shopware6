<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search\Request\Handler;

use Nosto\NostoIntegration\Struct\IdToFieldMapping;

/**
 * Rewrites Nosto field-path filter parameters into the canonical facet-id parameters the
 * storefront filter panel understands.
 *
 * Nosto's autocomplete emits `?products.filter.<field>=<value>`, but the filter panel computes
 * its selected state client-side from window.location.search keyed by facet id, and Shopware's
 * listing JS rebuilds the query string from registered filter plugins only — so a field-path
 * parameter is invisible to the panel and is dropped on the shopper's first interaction.
 * Redirecting to the canonical URL fixes both, and removes the need for merchants to maintain a
 * `serpUrlMapping` in their Nosto search templates. See ADS-5261.
 */
class CanonicalFilterUrlBuilder
{
    /**
     * Mirrors FilterHandler::MIN_PREFIX / ::MAX_PREFIX, which are protected there and so cannot
     * be reused from the outside. Keep the two in sync.
     */
    protected const MIN_PREFIX = 'min-';

    protected const MAX_PREFIX = 'max-';

    public function __construct(
        private readonly NostoFieldFilterParser $parser = new NostoFieldFilterParser(),
    ) {
    }

    /**
     * Returns the canonical URL for $path + $queryString, or null when there is nothing to
     * canonicalise — no field-path filters, or none of them resolves to a known facet id.
     * Returning null is important: it prevents a pointless redirect, and a redirect loop.
     */
    public function build(string $path, ?string $queryString, ?IdToFieldMapping $mapping): ?string
    {
        // After a redirect the URL carries no 'products.filter.*' parameter any more, so this
        // returns [] and no second redirect can be issued. That is the loop guard.
        if ($this->parser->parseQueryString($queryString) === []) {
            return null;
        }

        if ($mapping === null) {
            return null;
        }

        /** @var array<string, string> $facetIdsByField */
        $facetIdsByField = array_flip($mapping->getMap());

        $pairs = [];
        $rewritten = false;

        foreach (explode('&', (string) $queryString) as $pair) {
            if ($pair === '') {
                continue;
            }

            $canonical = $this->canonicalise($pair, $facetIdsByField);

            if ($canonical === null) {
                // Not a resolvable field-path filter: keep the shopper's parameter untouched.
                // Re-encoding it would risk changing values that are already correctly escaped.
                $pairs[] = $pair;
                continue;
            }

            foreach ($canonical as $canonicalPair) {
                $pairs[] = $canonicalPair;
            }

            $rewritten = true;
        }

        if (!$rewritten) {
            return null;
        }

        return $path . '?' . implode('&', $pairs);
    }

    /**
     * @param array<string, string> $facetIdsByField
     * @return string[]|null the canonical replacement pairs, or null when $pair must be kept
     *                       verbatim. An empty array means the parameter is dropped, which
     *                       happens only for an unparseable field-path parameter that could
     *                       not have been applied as a filter either.
     */
    private function canonicalise(string $pair, array $facetIdsByField): ?array
    {
        if (!str_contains($pair, '=')) {
            return null;
        }

        [$name, $value] = explode('=', $pair, 2);

        $name = urldecode($name);
        if (!NostoFieldFilterParser::supports($name)) {
            return null;
        }

        // Exactly one urldecode per value, matching NostoFieldFilterParser::parseQueryString():
        // a literal '~' inside a value arrives double-encoded and must stay '%7E' here.
        $filter = $this->parser->parse($name, urldecode($value));
        if ($filter === null) {
            return [];
        }

        $facetId = $facetIdsByField[$filter->field] ?? null;
        if ($facetId === null || $facetId === '') {
            return null;
        }

        return $filter->isRange()
            ? $this->rangePairs($facetId, $filter)
            : $this->valuePairs($facetId, $filter);
    }

    /**
     * @return string[]
     */
    private function valuePairs(string $facetId, NostoFieldFilter $filter): array
    {
        return [
            urlencode($facetId) . '=' . urlencode(implode(FilterHandler::FILTER_DELIMITER, $filter->values)),
        ];
    }

    /**
     * Only the first range is representable, exactly as FilterHandler does when applying it.
     * The panel has no exclusive form, so an exclusive bound canonicalises to an inclusive one.
     *
     * @return string[]
     */
    private function rangePairs(string $facetId, NostoFieldFilter $filter): array
    {
        $range = $filter->ranges[0];
        $lower = $range['gte'] ?? $range['gt'] ?? null;
        $upper = $range['lte'] ?? $range['lt'] ?? null;

        $pairs = [];

        if ($lower !== null) {
            $pairs[] = self::MIN_PREFIX . urlencode($facetId) . '=' . urlencode($lower);
        }
        if ($upper !== null) {
            $pairs[] = self::MAX_PREFIX . urlencode($facetId) . '=' . urlencode($upper);
        }

        return $pairs;
    }
}
