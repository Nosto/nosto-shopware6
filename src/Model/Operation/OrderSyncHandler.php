<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Operation;

use Nosto\NostoIntegration\Async\OrderSyncMessage;
use Nosto\NostoIntegration\Model\Nosto\Account;
use Nosto\NostoIntegration\Model\Nosto\Entity\Order\BuilderInterface as NostoOrderBuilderInterface;
use Nosto\NostoIntegration\Model\Nosto\Entity\Order\Event\NostoOrderCriteriaEvent;
use Nosto\NostoIntegration\Model\Nosto\Entity\Order\Status\BuilderInterface as NostoOrderStatusBuilderInterface;
use Nosto\NostoIntegration\Model\Operation\Event\BeforeOrderCreatedEvent;
use Nosto\Operation\AbstractGraphQLOperation;
use Nosto\Operation\Order\{OrderCreate, OrderStatus};
use Nosto\Scheduler\Model\Job\{JobHandlerInterface, JobResult};
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\{EntityCollection, EntityRepository};
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class OrderSyncHandler implements JobHandlerInterface
{
    public const HANDLER_CODE = 'nosto-integration-order-sync';

    private EntityRepository $orderRepository;

    private Account\Provider $accountProvider;

    private NostoOrderBuilderInterface $nostoOrderbuilder;

    private NostoOrderStatusBuilderInterface $nostoOrderStatusBuilder;

    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        EntityRepository $orderRepository,
        Account\Provider $accountProvider,
        NostoOrderBuilderInterface $nostoOrderbuilder,
        NostoOrderStatusBuilderInterface $nostoOrderStatusBuilder,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->orderRepository = $orderRepository;
        $this->accountProvider = $accountProvider;
        $this->nostoOrderbuilder = $nostoOrderbuilder;
        $this->nostoOrderStatusBuilder = $nostoOrderStatusBuilder;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param OrderSyncMessage $message
     */
    public function execute(object $message): JobResult
    {
        $operationResult = new JobResult();
        foreach ($this->accountProvider->all($message->getContext()) as $account) {
            $accountOperationResult = $this->doOperation($account, $message->getContext(), $message);
            foreach ($accountOperationResult->getErrors() as $error) {
                $operationResult->addError($error);
            }
        }

        return $operationResult;
    }

    private function doOperation(Account $account, Context $context, object $message): JobResult
    {
        $result = new JobResult();
        foreach ($this->getOrders($context, $message->getNewOrderIds()) as $order) {
            try {
                $this->sendNewOrder($order, $account, $context);
            } catch (\Throwable $e) {
                $result->addError($e);
            }
        }
        foreach ($this->getOrders($context, $message->getUpdatedOrderIds()) as $order) {
            try {
                $this->sendUpdatedOrder($order, $account);
            } catch (\Throwable $e) {
                $result->addError($e);
            }
        }

        return new JobResult();
    }

    private function getOrders(Context $context, array $orderIds): EntityCollection
    {
        $criteria = new Criteria();
        $criteria->addAssociation('stateMachineState');
        $criteria->addAssociation('orderCustomer');
        $criteria->addAssociation('currency');
        $criteria->addAssociation('addresses');
        $criteria->addAssociation('billingAddress');
        $criteria->addAssociation('transactions.paymentMethod');
        $criteria->addAssociation('lineItems.orderLineItem.product');
        $criteria->addFilter(new EqualsAnyFilter('id', $orderIds));
        $this->eventDispatcher->dispatch(new NostoOrderCriteriaEvent($criteria, $context));
        return $this->orderRepository->search($criteria, $context)->getEntities();
    }

    private function sendNewOrder(OrderEntity $order, Account $account, Context $context): void
    {
        $nostoOrder = $this->nostoOrderbuilder->build($order, $context);
        $nostoCustomerId = $order->getOrderCustomer()->getCustomerId();
        $nostoCustomerIdentifier = AbstractGraphQLOperation::IDENTIFIER_BY_REF;
        $operation = new OrderCreate(
            $nostoOrder,
            $account->getNostoAccount(),
            $nostoCustomerIdentifier,
            $nostoCustomerId
        );
        $this->eventDispatcher->dispatch(new BeforeOrderCreatedEvent($operation, $context));
        $operation->execute();
    }

    private function sendUpdatedOrder(OrderEntity $order, Account $account): void
    {
        $nostoOrderStatus = $this->nostoOrderStatusBuilder->build($order);
        $operation = new OrderStatus($account->getNostoAccount(), $nostoOrderStatus);
        $operation->execute();
    }
}
