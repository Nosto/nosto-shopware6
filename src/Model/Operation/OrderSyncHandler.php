<?php

namespace Od\NostoIntegration\Model\Operation;

use Nosto\Operation\Order\OrderCreate;
use Nosto\Operation\Order\OrderStatus;
use Od\NostoIntegration\Async\EventsWriter;
use Od\NostoIntegration\Async\OrderSyncMessage;
use Od\NostoIntegration\Model\Nosto\Account;
use Od\NostoIntegration\Model\Nosto\Entity\Order\Builder as NostoOrderBuilder;
use Od\NostoIntegration\Model\Nosto\Entity\Order\Status\Builder as NostoOrderStatusBuilder;
use Od\Scheduler\Model\Job\JobHandlerInterface;
use Od\Scheduler\Model\Job\JobResult;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;

class OrderSyncHandler implements JobHandlerInterface
{
    public const HANDLER_CODE = 'od-nosto-order-sync';
    private EntityRepositoryInterface $orderRepository;
    private Account\Provider $accountProvider;
    private NostoOrderBuilder $nostoOrderbuilder;
    private NostoOrderStatusBuilder $nostoOrderStatusBuilder;

    public function __construct(
        EntityRepositoryInterface $orderRepository,
        Account\Provider $accountProvider,
        NostoOrderBuilder $nostoOrderbuilder,
        NostoOrderStatusBuilder $nostoOrderStatusBuilder
    ) {
        $this->orderRepository = $orderRepository;
        $this->accountProvider = $accountProvider;
        $this->nostoOrderbuilder = $nostoOrderbuilder;
        $this->nostoOrderStatusBuilder = $nostoOrderStatusBuilder;
    }

    /**
     * @param OrderSyncMessage $message
     *
     * @return JobResult
     */

    public function execute(object $message): JobResult
    {
        $operationResult = new JobResult();
        $context = Context::createDefaultContext();

        foreach ($this->accountProvider->all() as $account) {
            $accountOperationResult = $this->doOperation($account, $context, $message->getOrderIds());
            foreach ($accountOperationResult->getErrors() as $error) {
                $operationResult->addError($error);
            }
        }

        return $operationResult;
    }

    private function doOperation(Account $account, Context $context, array $orderIds): JobResult
    {
        $criteria = new Criteria();
        $criteria->addAssociation('stateMachineState');
        $criteria->addAssociation('orderCustomer');
        $criteria->addAssociation('currency');
        $criteria->addAssociation('addresses');
        $criteria->addAssociation('billingAddress');
        $criteria->addAssociation('transactions');
        $criteria->addAssociation('od_nosto_entity_changelog.entityType');
        $criteria->addFilter(new EqualsAnyFilter('id', $orderIds));
        $orders = $this->orderRepository->search($criteria, $context);
        if ($orders->count() !== 0) {
            foreach ($orders as $order) {
                if ($order->getEntityType() === EventsWriter::ORDER_ENTITY_PLACED_NAME) {
                    $this->sendNewOrder($order, $account);
                } else {
                    $this->sendOrderStatusUpdated($order, $account);
                }
            }
        }

        return new JobResult();
    }

    private function sendNewOrder(
        OrderEntity $order,
        Account $account
    ): void {
        $nostoOrder = $this->nostoOrderbuilder->build($order);
        $operation = new OrderCreate($nostoOrder, $account->getNostoAccount());
        $operation->execute();

    }

    private function sendOrderStatusUpdated(
        OrderEntity $order,
        Account $account
    ): void {
        $nostoOrderStatus = $this->nostoOrderStatusBuilder->build($order);
        $operation = new OrderStatus($account->getNostoAccount(), $nostoOrderStatus);
        $operation->execute();
    }
}