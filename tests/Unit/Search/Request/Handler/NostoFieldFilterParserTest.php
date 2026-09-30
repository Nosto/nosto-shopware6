<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Search\Request\Handler;

use Nosto\NostoIntegration\Search\Request\Handler\NostoFieldFilterParser;
use PHPUnit\Framework\TestCase;

final class NostoFieldFilterParserTest extends TestCase
{
    public function testRecognisesPrefixedParameter(): void
    {
        self::assertTrue(NostoFieldFilterParser::supports('products.filter.brand'));
    }

    public function testIgnoresShopwareParameters(): void
    {
        self::assertFalse(NostoFieldFilterParser::supports('q'));
        self::assertFalse(NostoFieldFilterParser::supports('sort'));
        self::assertFalse(NostoFieldFilterParser::supports('p'));
        self::assertFalse(NostoFieldFilterParser::supports('68836248ec4277760d058e18'));
        self::assertFalse(NostoFieldFilterParser::supports('min-price'));
    }

    public function testExtractsSimpleField(): void
    {
        $filter = (new NostoFieldFilterParser())->parse('products.filter.brand', 'Nosto');

        self::assertNotNull($filter);
        self::assertSame('brand', $filter->field);
        self::assertSame(['Nosto'], $filter->values);
        self::assertFalse($filter->isRange());
    }

    public function testExtractsNestedSkuCustomField(): void
    {
        $filter = (new NostoFieldFilterParser())->parse(
            'products.filter.skus.customFields.größe',
            '100x200cm',
        );

        self::assertNotNull($filter);
        self::assertSame('skus.customFields.größe', $filter->field);
        self::assertSame(['100x200cm'], $filter->values);
    }

    public function testReturnsNullForUnprefixedKey(): void
    {
        self::assertNull((new NostoFieldFilterParser())->parse('brand', 'Nosto'));
    }

    public function testReturnsNullForEmptyFieldOrValue(): void
    {
        $parser = new NostoFieldFilterParser();

        self::assertNull($parser->parse('products.filter.', 'Nosto'));
        self::assertNull($parser->parse('products.filter.brand', ''));
    }

    public function testRejectsFieldWithControlCharacters(): void
    {
        $parser = new NostoFieldFilterParser();

        self::assertNull($parser->parse("products.filter.\0nul", '1'));
        self::assertNull($parser->parse("products.filter.a\tb", '1'));
        self::assertNull($parser->parse('products.filter.a"b', '1'));
        self::assertNull($parser->parse('products.filter.a{b}', '1'));
    }

    public function testRejectsOverlongFieldName(): void
    {
        $parser = new NostoFieldFilterParser();

        self::assertNotNull($parser->parse('products.filter.' . str_repeat('a', 128), '1'));
        self::assertNull($parser->parse('products.filter.' . str_repeat('a', 129), '1'));
    }

    public function testAcceptsLocalisedAndNestedFieldNames(): void
    {
        $parser = new NostoFieldFilterParser();

        self::assertSame(
            'skus.customFields.größe',
            $parser->parse('products.filter.skus.customFields.größe', '1')?->field,
        );
        self::assertSame('brand', $parser->parse('products.filter.brand', '1')?->field);
        self::assertSame('custom field', $parser->parse('products.filter.custom field', '1')?->field);
        self::assertSame('size-eu', $parser->parse('products.filter.size-eu', '1')?->field);
        self::assertSame('size.eu', $parser->parse('products.filter.size::eu', '1')?->field);
    }

    public function testSplitsMultipleValuesOnDoublePipe(): void
    {
        $filter = (new NostoFieldFilterParser())->parse(
            'products.filter.skus.customFields.colour',
            'Blue||Red||White',
        );

        self::assertNotNull($filter);
        self::assertSame(['Blue', 'Red', 'White'], $filter->values);
    }

    public function testDoesNotSplitOnSinglePipe(): void
    {
        $filter = (new NostoFieldFilterParser())->parse('products.filter.brand', 'A|B');

        self::assertNotNull($filter);
        self::assertSame(['A|B'], $filter->values);
    }

    public function testUnescapesEncodedTildeInValues(): void
    {
        $filter = (new NostoFieldFilterParser())->parse('products.filter.brand', 'A%7EB');

        self::assertNotNull($filter);
        self::assertSame(['A~B'], $filter->values);
    }

    public function testDropsEmptySegmentsFromMultiValue(): void
    {
        $filter = (new NostoFieldFilterParser())->parse('products.filter.brand', 'A||||B');

        self::assertNotNull($filter);
        self::assertSame(['A', 'B'], $filter->values);
    }

    public function testParsesInclusiveRange(): void
    {
        $filter = (new NostoFieldFilterParser())->parse('products.filter.price', '10~20');

        self::assertNotNull($filter);
        self::assertTrue($filter->isRange());
        self::assertSame([[
            'gte' => '10',
            'lte' => '20',
        ]], $filter->ranges);
        self::assertSame([], $filter->values);
    }

    public function testParsesOpenEndedRanges(): void
    {
        $parser = new NostoFieldFilterParser();

        self::assertSame([[
            'gte' => '10',
        ]], $parser->parse('products.filter.price', '10~')->ranges);
        self::assertSame([[
            'lte' => '20',
        ]], $parser->parse('products.filter.price', '~20')->ranges);
    }

    public function testParsesExclusiveBoundsFromBrackets(): void
    {
        $filter = (new NostoFieldFilterParser())->parse('products.filter.price', '[10~20]');

        self::assertNotNull($filter);
        self::assertSame([[
            'gt' => '10',
            'lt' => '20',
        ]], $filter->ranges);
    }

    public function testParsesMultipleCommaSeparatedRanges(): void
    {
        $filter = (new NostoFieldFilterParser())->parse('products.filter.price', '10~20,30~40');

        self::assertNotNull($filter);
        self::assertSame(
            [[
                'gte' => '10',
                'lte' => '20',
            ], [
                'gte' => '30',
                'lte' => '40',
            ]],
            $filter->ranges,
        );
    }

    public function testParsesLegacyRangeSeparator(): void
    {
        $filter = (new NostoFieldFilterParser())->parse('products.filter.price', '10-+20');

        self::assertNotNull($filter);
        self::assertSame([[
            'gte' => '10',
            'lte' => '20',
        ]], $filter->ranges);
    }

    public function testEscapedTildeIsAValueNotARange(): void
    {
        $filter = (new NostoFieldFilterParser())->parse('products.filter.brand', 'A%7EB');

        self::assertNotNull($filter);
        self::assertFalse($filter->isRange());
        self::assertSame(['A~B'], $filter->values);
    }

    public function testBareValueWithNoSeparatorIsNotARange(): void
    {
        $filter = (new NostoFieldFilterParser())->parse('products.filter.brand', '100x200cm');

        self::assertNotNull($filter);
        self::assertFalse($filter->isRange());
        self::assertSame(['100x200cm'], $filter->values);
    }

    public function testUnescapesDoubleColonInFieldSegments(): void
    {
        $filter = (new NostoFieldFilterParser())->parse('products.filter.size::eu', '42');

        self::assertNotNull($filter);
        self::assertSame('size.eu', $filter->field);
    }

    public function testLeavesNestedPathSeparatorsIntact(): void
    {
        $filter = (new NostoFieldFilterParser())->parse(
            'products.filter.skus.customFields.größe',
            '100x200cm',
        );

        self::assertNotNull($filter);
        self::assertSame('skus.customFields.größe', $filter->field);
    }

    public function testParsesFieldPathFiltersFromRawQueryString(): void
    {
        // Exactly what Nosto's search-js puts on the wire: dots unencoded, '||' as %7C%7C,
        // range separator as %7E, and a literal tilde double-encoded as %257E.
        $queryString = 'search=main'
            . '&products.filter.brand=A%257EB'
            . '&products.filter.colour=Blue%7C%7CRed'
            . '&products.filter.price=10%7E20'
            . '&products.filter.skus.customFields.gr%C3%B6%C3%9Fe=140x200cm'
            . '&products.filter.legacy=10-%2B20'
            . '&products.filter.name=Blue+Steel';

        $filters = (new NostoFieldFilterParser())->parseQueryString($queryString);

        self::assertCount(6, $filters);

        self::assertSame('brand', $filters[0]->field);
        self::assertSame(['A~B'], $filters[0]->values);
        self::assertFalse($filters[0]->isRange());

        self::assertSame('colour', $filters[1]->field);
        self::assertSame(['Blue', 'Red'], $filters[1]->values);

        self::assertSame('price', $filters[2]->field);
        self::assertSame([[
            'gte' => '10',
            'lte' => '20',
        ]], $filters[2]->ranges);

        self::assertSame('skus.customFields.größe', $filters[3]->field);
        self::assertSame(['140x200cm'], $filters[3]->values);

        // The legacy '-+' separator arrives form-urlencoded as '10-%2B20'.
        self::assertSame('legacy', $filters[4]->field);
        self::assertSame([[
            'gte' => '10',
            'lte' => '20',
        ]], $filters[4]->ranges);

        // Form-urlencoding puts a space on the wire as '+', so urldecode (not rawurldecode)
        // is what restores it.
        self::assertSame('name', $filters[5]->field);
        self::assertSame(['Blue Steel'], $filters[5]->values);
    }

    public function testParseQueryStringIgnoresNonNostoParameters(): void
    {
        $filters = (new NostoFieldFilterParser())->parseQueryString('search=main&sort=name-asc&p=2');

        self::assertSame([], $filters);
    }

    public function testParseQueryStringHandlesEmptyAndNullInput(): void
    {
        $parser = new NostoFieldFilterParser();

        self::assertSame([], $parser->parseQueryString(null));
        self::assertSame([], $parser->parseQueryString(''));
        self::assertSame([], $parser->parseQueryString('&&'));
    }

    public function testParseQueryStringSkipsValuelessParameter(): void
    {
        $filters = (new NostoFieldFilterParser())->parseQueryString('products.filter.brand');

        self::assertSame([], $filters);
    }

    public function testRejectsNonNumericRangeBoundsAndFallsBackToValue(): void
    {
        $parser = new NostoFieldFilterParser();

        // A value delimiter inside what looks like a range means it is not a range.
        $filter = $parser->parse('products.filter.brand', '10~20||30');
        self::assertNotNull($filter);
        self::assertFalse($filter->isRange());
        self::assertSame(['10~20', '30'], $filter->values);

        // Non-numeric bounds are not a range either.
        $filter = $parser->parse('products.filter.brand', 'abc~def');
        self::assertNotNull($filter);
        self::assertFalse($filter->isRange());
        self::assertSame(['abc~def'], $filter->values);
    }

    public function testTrimsNumericRangeBounds(): void
    {
        $filter = (new NostoFieldFilterParser())->parse('products.filter.price', ' 10 ~ 20 ');

        self::assertNotNull($filter);
        self::assertTrue($filter->isRange());
        self::assertSame([[
            'gte' => '10',
            'lte' => '20',
        ]], $filter->ranges);
    }

    public function testDetectsExclusiveBoundsOnlyAtTheEdges(): void
    {
        // A bracket in the middle of a bound is not an exclusivity marker; the bound is
        // then non-numeric, so this is a value rather than a range.
        $filter = (new NostoFieldFilterParser())->parse('products.filter.price', '1[0~20');

        self::assertNotNull($filter);
        self::assertFalse($filter->isRange());
    }

    public function testIgnoresEmptyCommaSegmentsInRanges(): void
    {
        $parser = new NostoFieldFilterParser();

        // A stray comma carries no information and must not invalidate the range. Degrading
        // to a terms filter here would search for the literal string and match nothing.
        foreach (['10~20,', ',10~20'] as $rawValue) {
            $filter = $parser->parse('products.filter.price', $rawValue);
            self::assertNotNull($filter, $rawValue);
            self::assertTrue($filter->isRange(), $rawValue);
            self::assertSame([[
                'gte' => '10',
                'lte' => '20',
            ]], $filter->ranges, $rawValue);
        }

        foreach (['10~20,,30~40', '10~20,30~40,'] as $rawValue) {
            $filter = $parser->parse('products.filter.price', $rawValue);
            self::assertNotNull($filter, $rawValue);
            self::assertSame(
                [[
                    'gte' => '10',
                    'lte' => '20',
                ], [
                    'gte' => '30',
                    'lte' => '40',
                ]],
                $filter->ranges,
                $rawValue,
            );
        }
    }

    public function testStillRejectsNonEmptyGarbageSegments(): void
    {
        // Non-empty unparseable segments must still invalidate the whole parameter.
        $filter = (new NostoFieldFilterParser())->parse('products.filter.price', '10~20,abc~def');

        self::assertNotNull($filter);
        self::assertFalse($filter->isRange());
        self::assertSame(['10~20,abc~def'], $filter->values);
    }
}
