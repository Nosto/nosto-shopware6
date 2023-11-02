<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Service\CategoryMerchandising\Translator;

use Nosto\Operation\Recommendation\IncludeFilters;

interface TranslatorInterface
{
    public function translate(IncludeFilters $includeFilters): IncludeFilters;

    public function isSupport($filters): bool;
}
