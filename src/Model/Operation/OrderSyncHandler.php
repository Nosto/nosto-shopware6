<?php

namespace Od\NostoIntegration\Model\Operation;

use Od\NostoIntegration\Async\OrderSyncMessage;
use Od\Scheduler\Model\Job\JobHandlerInterface;
use Od\Scheduler\Model\Job\JobResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;

class OrderSyncHandler implements JobHandlerInterface
{
    public const HANDLER_CODE = 'od-nosto-order-sync';
    private EntityRepositoryInterface $orderRepository;

    public function __construct(EntityRepositoryInterface $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    /**
     * @param OrderSyncMessage $message
     *
     * @return JobResult
     */

    public function execute(object $message): JobResult
    {
        $orderIds = $message->getOrderIds();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('id', $orderIds));

        $context = Context::createDefaultContext();

        $orders = $this->orderRepository->search($criteria, $context);

        return new JobResult();

    }
}