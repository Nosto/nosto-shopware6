<?php

namespace Od\NostoIntegration\Model\Operation;

use Od\NostoIntegration\Async\ProductSyncMessage;
use Od\Scheduler\Model\Job\JobHandlerInterface;
use Od\Scheduler\Model\Job\JobResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;

class ProductSyncHandler implements JobHandlerInterface
{
    public const HANDLER_CODE = 'od-nosto-product-sync';

    private EntityRepositoryInterface $productRepository;

    public function __construct(EntityRepositoryInterface $productRepository) {
       $this->productRepository = $productRepository;
    }

    /**
     * @param ProductSyncMessage $message
     *
     * @return JobResult
     */
    public function execute(object $message): JobResult
    {
        $productIds = $message->getProductIds();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('id', $productIds));
        $context = Context::createDefaultContext();

        $products = $this->productRepository->search($criteria,$context);

        return new JobResult();
    }
}