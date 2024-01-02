<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Service\CategoryMerchandising\Translator;

use Nosto\Operation\Recommendation\IncludeFilters;
use Shopware\Core\Framework\Context;

class FilterTranslatorAggregate
{
    public function __construct(
        private readonly iterable $filterTranslators,
    ) {
    }

    public function buildIncludeFilters(array $postFilters, Context $context): IncludeFilters
    {
        $includeFilters = new IncludeFilters();
        foreach ($this->filterTranslators as $filterTranslator) {
            foreach ($postFilters as $filters) {
                if ($filterTranslator->isSupport($filters)) {
                    $filterTranslator->translate($includeFilters, $filters, $context);
                }
            }
        }

        return $includeFilters;
    }
}
