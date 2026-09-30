<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Search\Request\Handler;

/**
 * Decodes Nosto search-js filter query parameters into NostoFieldFilter objects.
 *
 * Nosto's autocomplete and SERP links serialise filters keyed by the Nosto field path
 * rather than by facet id, e.g.:
 *
 *     ?products.filter.skus.customFields.größe=100x200cm
 *
 * This class is intentionally dependency-free so the (fiddly) format rules are covered
 * by plain unit tests.
 */
class NostoFieldFilterParser
{
    public const FIELD_PREFIX = 'products.filter.';

    public const VALUE_DELIMITER = '||';

    protected const ESCAPED_TILDE = '%7E';

    protected const RANGE_SEPARATOR = '~';

    protected const LEGACY_RANGE_SEPARATOR = '-+';

    protected const RANGE_DELIMITER = ',';

    protected const SEGMENT_DOT_ESCAPE = '::';

    /**
     * A Nosto field path is a dot-separated list of attribute names, e.g.
     * 'skus.customFields.größe'. Anything else is a malformed or hostile parameter and must
     * not reach the Nosto API. Unicode letters are allowed because merchants use localised
     * custom-field names.
     */
    protected const FIELD_PATTERN = '/^[\p{L}\p{N}_.\- ]{1,128}$/u';

    /**
     * PHP rewrites '.' to '_' in query-parameter names, so the same parameter also appears
     * in $request->query under this mangled prefix. Callers use this to discard it.
     */
    public const MANGLED_FIELD_PREFIX = 'products_filter_';

    public static function supports(string $key): bool
    {
        return str_starts_with($key, self::FIELD_PREFIX);
    }

    /**
     * Decodes every Nosto field-path filter out of a raw query string.
     *
     * This MUST be given the raw query string (Symfony: $request->server->get('QUERY_STRING')),
     * not $request->query. PHP rewrites '.' to '_' in parameter names, so a parameter named
     * 'products.filter.brand' is only ever visible as 'products_filter_brand' in the parsed
     * bag and could never be recognised.
     *
     * Exactly one urldecode is applied per name and per value. That is deliberate: Nosto
     * escapes a literal '~' in a value to the text '%7E' before percent-encoding, so it
     * arrives here double-encoded and must still be '%7E' when parseValues() runs. Decoding
     * twice would turn a literal tilde into a range separator.
     *
     * It must be urldecode() and not rawurldecode(): under form-urlencoding '+' means space,
     * so rawurldecode() would break every value containing one ('Blue Steel' arrives as
     * 'Blue+Steel'). The legacy '-+' range separator is therefore sent as '10-%2B20', which
     * urldecode() restores correctly.
     *
     * @return NostoFieldFilter[]
     */
    public function parseQueryString(?string $queryString): array
    {
        if ($queryString === null || $queryString === '') {
            return [];
        }

        $filters = [];

        foreach (explode('&', $queryString) as $pair) {
            if ($pair === '' || !str_contains($pair, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $pair, 2);

            $name = urldecode($name);
            if (!self::supports($name)) {
                continue;
            }

            $filter = $this->parse($name, urldecode($value));
            if ($filter !== null) {
                $filters[] = $filter;
            }
        }

        return $filters;
    }

    public function parse(string $key, string $rawValue): ?NostoFieldFilter
    {
        if (!self::supports($key)) {
            return null;
        }

        $field = str_replace(
            self::SEGMENT_DOT_ESCAPE,
            '.',
            mb_substr($key, mb_strlen(self::FIELD_PREFIX)),
        );
        if ($field === '' || $rawValue === '') {
            return null;
        }

        if (preg_match(self::FIELD_PATTERN, $field) !== 1) {
            return null;
        }

        $ranges = $this->parseRanges($rawValue);
        if ($ranges !== []) {
            return new NostoFieldFilter($field, [], $ranges);
        }

        $values = $this->parseValues($rawValue);
        if ($values === []) {
            return null;
        }

        return new NostoFieldFilter($field, $values);
    }

    /**
     * Nosto joins multiple values for one field with '||' and escapes any literal '~'
     * (its range separator) as '%7E'.
     *
     * @return string[]
     */
    protected function parseValues(string $rawValue): array
    {
        $values = [];

        foreach (explode(self::VALUE_DELIMITER, $rawValue) as $value) {
            $value = str_replace(self::ESCAPED_TILDE, '~', $value);
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * Nosto serialises a range as '<lower><separator><upper>', with square brackets marking
     * an exclusive bound: '[10~20]' means gt=10, lt=20 while '10~20' means gte=10, lte=20.
     * Multiple ranges for one field are comma-separated. A literal '~' inside a value is
     * escaped as '%7E', so an unescaped separator is what distinguishes a range from a value.
     *
     * @return array<int, array<string, string>>
     */
    protected function parseRanges(string $rawValue): array
    {
        $separator = match (true) {
            str_contains($rawValue, self::LEGACY_RANGE_SEPARATOR) => self::LEGACY_RANGE_SEPARATOR,
            str_contains($rawValue, self::RANGE_SEPARATOR) => self::RANGE_SEPARATOR,
            default => null,
        };

        if ($separator === null) {
            return [];
        }

        $ranges = [];

        foreach (explode(self::RANGE_DELIMITER, $rawValue) as $rawRange) {
            if ($rawRange === '') {
                continue; // an empty segment carries no information
            }

            $range = $this->parseRange($rawRange, $separator);
            if ($range === []) {
                // A non-empty unparseable segment means this is not a range parameter.
                return [];
            }

            $ranges[] = $range;
        }

        return $ranges;
    }

    /**
     * A range bound is always numeric in this format. Anything else means the parameter is
     * not really a range (e.g. 'abc~def', or '10~20||30' where a value delimiter leaks in),
     * and the caller falls back to treating it as a value. That keeps malformed, user-supplied
     * URLs from sending nonsense bounds to the Nosto API.
     *
     * @return array<string, string>
     */
    protected function parseRange(string $rawRange, string $separator): array
    {
        if (!str_contains($rawRange, $separator)) {
            return [];
        }

        [$lower, $upper] = explode($separator, $rawRange, 2);

        $lowerIsExclusive = str_starts_with(trim($lower), '[');
        $upperIsExclusive = str_ends_with(trim($upper), ']');

        $lower = $this->cleanBound($lower);
        $upper = $this->cleanBound($upper);

        // Both bounds empty is not a range; a present-but-non-numeric bound invalidates it.
        if ($lower === '' && $upper === '') {
            return [];
        }
        if (($lower !== '' && !is_numeric($lower)) || ($upper !== '' && !is_numeric($upper))) {
            return [];
        }

        $range = [];

        if ($lower !== '') {
            $range[$lowerIsExclusive ? 'gt' : 'gte'] = $lower;
        }
        if ($upper !== '') {
            $range[$upperIsExclusive ? 'lt' : 'lte'] = $upper;
        }

        return $range;
    }

    /**
     * Bracket/brace/quote markers only ever wrap a bound, so they are stripped from the edges
     * only: a bracket in the middle (e.g. '1[0') is not a marker, and leaving it in place lets
     * the numeric guard in parseRange() reject the malformed bound.
     */
    protected function cleanBound(string $bound): string
    {
        return trim($bound, "[{]}' \t\n\r\0\x0B");
    }
}
