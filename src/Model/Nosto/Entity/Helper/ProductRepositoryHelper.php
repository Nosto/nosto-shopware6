<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Entity\Helper;

use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;

class ProductRepositoryHelper
{
    /**
     * @param EntityRepository<ProductCollection> $repository
     * @return iterable<EntitySearchResult<ProductCollection>>
     */
    public static function searchChunked(
        Criteria $criteria,
        int $limit,
        Context $context,
        EntityRepository $repository,
    ): iterable {
        $ids = $criteria->getIds();
        if (empty($ids)) {
            return [];
        }

        foreach (array_chunk($ids, $limit) as $chunk) {
            $criteria->setIds($chunk);
            yield self::search($criteria, $context, $repository);
        }
    }

    /**
     * @param EntityRepository<ProductCollection> $repository
     * @return EntitySearchResult<ProductCollection>
     */
    public static function search(
        Criteria $criteria,
        Context $context,
        EntityRepository $repository,
    ): EntitySearchResult {
        if (!$criteria->hasAssociation('children')) {
            return $repository->search($criteria, $context);
        }

        $childCriteria = $criteria->getAssociation('children');
        $criteria->removeAssociation('children');

        $parents = $repository->search($criteria, $context);
        foreach ($parents->getEntities() as $parent) {
            $parent->setChildren(new ProductCollection());
        }

        $parentIds = $parents->getIds();
        $childCriteria->addFilter(new EqualsAnyFilter('parentId', $parentIds));
        if ($criteria->getTitle()) {
            $childCriteria->setTitle($criteria->getTitle() . '::associations::children');
        }

        $childCriteria->setLimit(100);

        $repositoryIterator = new RepositoryIterator($repository, $context, $childCriteria);

        $childProducts = new ProductCollection();
        do {
            $result = $repositoryIterator->fetch();
            if ($result === null) {
                break;
            }

            $childProducts->merge($result->getEntities());
        } while ($result->count() > 0);

        foreach ($childProducts as $childProduct) {
            $parentId = $childProduct->getParentId();
            $parent = $parents->get($parentId ?? '');
            if (!$parent) {
                continue;
            }

            $parent->getChildren()?->add($childProduct);
        }

        return $parents;
    }
}
