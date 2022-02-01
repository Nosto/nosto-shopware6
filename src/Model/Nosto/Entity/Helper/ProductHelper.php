<?php declare(strict_types=1);

namespace Od\NostoIntegration\Model\Nosto\Entity\Helper;

use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\CountResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProductHelper
{
    private SalesChannelRepositoryInterface $productRepository;
    private EntityRepositoryInterface $reviewRepository;

    public function __construct(
        SalesChannelRepositoryInterface $productRepository,
        EntityRepositoryInterface $reviewRepository
    ) {
        $this->productRepository = $productRepository;
        $this->reviewRepository = $reviewRepository;
    }

    public function getReviewsCount(SalesChannelProductEntity $product, SalesChannelContext $context): int
    {
        $reviewCriteria = new Criteria();
        $reviewCriteria->addFilter(
            new MultiFilter(MultiFilter::CONNECTION_OR, [
                new EqualsFilter('product.id', $product->getId()),
                new EqualsFilter('product.parentId', $product->getId()),
            ])
        );
        $reviewCriteria->addAggregation(new CountAggregation('review-count', 'id'));
        $aggregation = $this->reviewRepository->aggregate($reviewCriteria, $context->getContext())->get('review-count');

        return $aggregation instanceof CountResult ? $aggregation->getCount() : 0;
    }

    public function reloadProduct(SalesChannelProductEntity $product, SalesChannelContext $context)
    {
        $criteria = new Criteria();
        $criteria->addAssociation('media');
        $criteria->addAssociation('options.group');
        $criteria->addAssociation('children.media');
        $criteria->addAssociation('children.options.group');
        $criteria->addAssociation('manufacturer');
        $criteria->addAssociation('categoriesRo');
        $criteria->addFilter(new EqualsFilter('id', $product->getId()));

        return $this->productRepository->search($criteria, $context)->first();
    }
}
