<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Service\CategoryMerchandising\Translator;

use Nosto\Operation\Recommendation\IncludeFilters;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;

class PriceFilterTranslator implements TranslatorInterface
{
    public const PRICE = "product.cheapestPrice";

    public function translate(
        IncludeFilters $includeFilters,
        RangeFilter $filters = null,
    ): IncludeFilters {
        if ($filters) {
            $includeFilters->setPrice($filters->getParameter("gte"), $filters->getParameter("lte"));
        }

        return $includeFilters;
    }

    public function isSupport($filters): bool
    {
        return $filters instanceof RangeFilter && $filters->getField() === self::PRICE;
    }
}
