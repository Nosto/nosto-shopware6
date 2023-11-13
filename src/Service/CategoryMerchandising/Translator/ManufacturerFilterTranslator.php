<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Service\CategoryMerchandising\Translator;

use Nosto\Operation\Recommendation\IncludeFilters;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;

class ManufacturerFilterTranslator implements TranslatorInterface
{
    public const MANUFACTURER = "product.manufacturerId";

    private EntityRepository $manufacturerRepository;

    public function __construct(EntityRepository $manufacturerRepository)
    {
        $this->manufacturerRepository = $manufacturerRepository;
    }

    public function translate(
        IncludeFilters $includeFilters,
        EqualsAnyFilter $filters = null,
        Context $context = null
    ): IncludeFilters {
        if (!$filters) {
            return $includeFilters;
        }
        $manufacturerIds = array_values($filters->getValue());
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('id', $manufacturerIds));
        $manufacturers = $this->manufacturerRepository->search($criteria, $context)->getEntities();
        $manufacturerNames = [];
        foreach ($manufacturers as $manufacturer) {
            $manufacturerNames[] = $manufacturer->getName();
        }

        $includeFilters->setBrands(array_map(static function ($name) {
            return $name;
        }, $manufacturerNames));

        return $includeFilters;
    }

    public function isSupport($filters): bool
    {
        return $filters instanceof EqualsAnyFilter && $filters->getField() === self::MANUFACTURER;
    }
}
