<?php declare(strict_types=1);

namespace Od\NostoIntegration\Service\CategoryMerchandising\Translator;

use Nosto\Operation\Recommendation\IncludeFilters;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class ShippingFreeFilterTranslator
{
    public const SHIPPING = "product.shippingFree";

    public function translate(IncludeFilters $includeFilters): IncludeFilters
    {
        $includeFilters->setCustomFields("Shipping Free", ["true"]);

        return $includeFilters;
    }

    public function isSupport($filters): bool
    {
        return $filters instanceof EqualsFilter && $filters->getField() === self::SHIPPING;
    }
}