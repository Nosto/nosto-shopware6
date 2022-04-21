<?php declare(strict_types=1);

namespace Od\NostoIntegration\Service\CategoryMerchandising\Translator;

use Nosto\Operation\Recommendation\IncludeFilters;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;

class ManufacturerFilterTranslator
{
    public const MANUFACTURER = "product.manufacturerId";

    private EntityRepositoryInterface $manufacturerRepository;

    public function __construct(EntityRepositoryInterface $manufacturerRepository)
    {
        $this->manufacturerRepository = $manufacturerRepository;
    }

    public function translate(
        IncludeFilters $includeFilters,
        EqualsAnyFilter $filters,
        Context $context
    ): IncludeFilters {
        $manufacturerIds = [];
        foreach ($filters->getValue() as $filter) {
            $manufacturerIds[] = $filter;
        }
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('id', $manufacturerIds));
        $manufacturers = $this->manufacturerRepository->search($criteria, $context)->getEntities();
        foreach ($manufacturers as $manufacturer) {
            $includeFilters->setBrands([$manufacturer->getName()]);
        }

        return $includeFilters;
    }

    public function isSupport($filters): bool
    {
        return $filters instanceof EqualsAnyFilter && $filters->getField() === self::MANUFACTURER;
    }
}