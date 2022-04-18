<?php declare(strict_types=1);

namespace Od\NostoIntegration\Service;

use Nosto\Result\Graphql\Recommendation\CategoryMerchandisingResult;

class CategoryMerchandisingResultTranslator
{
    public function getProductIds(CategoryMerchandisingResult $result): array
    {
        $productIds = [];
        foreach ($result->getResultSet() as $item) {
            if ($item->getProductId()) {
                $productIds[$item->getProductId()] = [
                    'primaryKey' => $item->getProductId(),
                    'data' => [
                        'id' => $item->getProductId()
                    ]
                ];
            }
        }

        return $productIds;
    }
}