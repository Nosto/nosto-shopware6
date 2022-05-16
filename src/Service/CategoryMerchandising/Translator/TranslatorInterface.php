<?php

namespace Od\NostoIntegration\Service\CategoryMerchandising\Translator;

use Nosto\Operation\Recommendation\IncludeFilters;

interface TranslatorInterface
{
    public function translate(IncludeFilters $includeFilters): IncludeFilters;

    public function isSupport($filters): bool;
}