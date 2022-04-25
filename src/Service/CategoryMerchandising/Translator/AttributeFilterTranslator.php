<?php declare(strict_types=1);

namespace Od\NostoIntegration\Service\CategoryMerchandising\Translator;

use Nosto\Operation\Recommendation\IncludeFilters;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\{EqualsAnyFilter, MultiFilter};

class AttributeFilterTranslator
{
    private EntityRepositoryInterface $propertyGroupOptionRepository;

    public function __construct(EntityRepositoryInterface $propertyGroupOptionRepository)
    {
        $this->propertyGroupOptionRepository = $propertyGroupOptionRepository;
    }

    public function translate(
        IncludeFilters $includeFilters,
        MultiFilter $filters,
        Context $context
    ): IncludeFilters {
        $optionAndPropertyIds = [];
        foreach ($filters->getQueries() as $filter) {
            /** @var MultiFilter $filter */
            $optionIds = $filter->getQueries()[0]->getValue();
            foreach ($optionIds as $optionId) {
                $optionAndPropertyIds[] = $optionId;
            }
        }
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('id', $optionAndPropertyIds));
        $criteria->addAssociation('group.id');
        $customFields = $this->propertyGroupOptionRepository->search($criteria, $context)->getEntities();
        foreach ($customFields as $field) {
            $includeFilters->setCustomFields($field->getGroup()->getName(), [$field->getName()]);
        }

        return $includeFilters;
    }

    public function isSupport($filters): bool
    {
        return $filters instanceof MultiFilter;
    }
}