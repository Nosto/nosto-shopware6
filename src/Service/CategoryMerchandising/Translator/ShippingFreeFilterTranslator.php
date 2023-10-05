<?php declare(strict_types=1);

namespace Nosto\NostoIntegration\Service\CategoryMerchandising\Translator;

use Nosto\Operation\Recommendation\IncludeFilters;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class ShippingFreeFilterTranslator implements TranslatorInterface
{
    public const SHIPPING_FREE_ATTR_NAME = 'Shipping Free';
    public const SHIPPING = "product.shippingFree";

    public function translate(IncludeFilters $includeFilters): IncludeFilters
    {
        $includeFilters->setCustomFields(self::SHIPPING_FREE_ATTR_NAME, ["true"]);

        return $includeFilters;
    }

    public function isSupport($filters): bool
    {
        return $filters instanceof EqualsFilter && $filters->getField() === self::SHIPPING;
    }
}
