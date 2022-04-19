<?php declare(strict_types=1);

namespace Od\NostoIntegration\Service\CategoryMerchandising\Translator;

use Nosto\Operation\Recommendation\IncludeFilters;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;

class FilterTranslator
{
    public const MANUFACTURER = "product.manufacturerId";
    public const PRICE = "product.cheapestPrice";
    public const SHIPPING = "product.shippingFree";

    private EntityRepositoryInterface $manufacturerRepository;
    private EntityRepositoryInterface $propertyGroupOptionRepository;

    public function __construct(
        EntityRepositoryInterface $manufacturerRepository,
        EntityRepositoryInterface $propertyGroupOptionRepository,
    ) {
        $this->manufacturerRepository = $manufacturerRepository;
        $this->propertyGroupOptionRepository = $propertyGroupOptionRepository;
    }

    public function setIncludeFilters(array $postFilters, Context $context): IncludeFilters
    {
        $includeFilters = new IncludeFilters();

        foreach ($postFilters as $filter) {
            if ($filter instanceof EqualsAnyFilter && $filter->getField() === self::MANUFACTURER) {
                $criteria = new Criteria();
                $criteria->addFilter(new EqualsFilter('id', $filter->getValue()[0]));
                $manufacturer = $this->manufacturerRepository->search($criteria, $context)->first();
                /** @var ProductManufacturerEntity $manufacturer */
                $includeFilters->setBrands([$manufacturer->getName()]);
            } elseif ($filter instanceof RangeFilter && $filter->getField() === self::PRICE) {
                /** @var RangeFilter $filter */
                $includeFilters->setPrice($filter->getParameter("gte"),$filter->getParameter("lte"));
            } elseif ($filter instanceof EqualsFilter && $filter->getField() === self::SHIPPING) {
                $includeFilters->setCustomFields("Shipping Free", ["true"]);
            } elseif ($filter instanceof MultiFilter) {
                $this->setOptionsAndProperties($filter, $includeFilters, $context);
            }
        }

        return $includeFilters;
    }

    private function setOptionsAndProperties(MultiFilter $filters, IncludeFilters $includeFilters, Context $context): void
    {
        $customFields = [];
        foreach ($filters->getQueries() as $filter) {
            $optionId = $filter->getQueries()[0]->getValue()[0];
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('id', $optionId));
            $criteria->addAssociation('group.id');
            $result = $this->propertyGroupOptionRepository->search($criteria, $context)->first();
            $customFields[$result->getGroup()->getName()] = $result->getName();
        }

        foreach($customFields as $attr => $value) {
            $includeFilters->setCustomFields($attr, [$value]);
        }
    }
}