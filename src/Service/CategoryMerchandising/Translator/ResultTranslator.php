<?php declare(strict_types=1);

namespace Nosto\NostoIntegration\Service\CategoryMerchandising\Translator;

use Nosto\Result\Graphql\Recommendation\{CategoryMerchandisingResult, ResultItem};

class ResultTranslator
{
    public function getProductIds(CategoryMerchandisingResult $result): array
    {
        return array_map(static function (ResultItem $item) {
            return [
                'primaryKey' => $item->getProductId(),
                'data' => ['id' => $item->getProductId()]
            ];
        }, iterator_to_array($result->getResultSet()));
    }
}