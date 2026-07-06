<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Operation;

use Nosto\NostoIntegration\Async\OrderSyncMessage;
use Nosto\NostoIntegration\Model\Nosto\Account;
use Nosto\NostoIntegration\Model\Nosto\Entity\Order\Builder as OrderBuilder;
use Nosto\NostoIntegration\Model\Nosto\Entity\Order\Event\NostoOrderCriteriaEvent;
use Nosto\NostoIntegration\Model\Nosto\Entity\Order\Status\Builder as OrderStatusBuilder;
use Nosto\NostoIntegration\Model\Operation\Event\BeforeOrderCreatedEvent;
use Nosto\NostoIntegration\Utils\NostoCriteriaFactory;
use Nosto\Operation\AbstractGraphQLOperation;
use Nosto\Operation\Order\{OrderCreate, OrderStatus};
use Nosto\Scheduler\Model\Job\{JobHandlerInterface, JobResult};
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\{EntityCollection, EntityRepository, Search\Filter\EqualsFilter};
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;

class OrderSyncHandler implements JobHandlerInterface
{
    public const HANDLER_CODE = 'nosto-integration-order-sync';

    public function __construct(
        private readonly AbstractSalesChannelContextFactory $channelContextFactory,
        private readonly EntityRepository $orderRepository,
        private readonly Account\Provider $accountProvider,
        private readonly OrderBuilder $nostoOrderbuilder,
        private readonly OrderStatusBuilder $nostoOrderStatusBuilder,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @param OrderSyncMessage $message
     */
    public function execute(object $message): JobResult
    {
        $operationResult = new JobResult();
        foreach ($this->accountProvider->all($message->getContext()) as $account) {
            $channelContext = $this->channelContextFactory->create(
                Uuid::randomHex(),
                $account->getChannelId(),
                [
                    SalesChannelContextService::LANGUAGE_ID => $account->getLanguageId(),
                ],
            );
            $accountOperationResult = $this->doOperation($account, $channelContext, $message);
            foreach ($accountOperationResult->getErrors() as $error) {
                $operationResult->addMessage($error);
            }
        }

        return $operationResult;
    }

    private function doOperation(Account $account, SalesChannelContext $context, object $message): JobResult
    {
        $result = new JobResult();
        $newOrderIds = $message->getNewOrderIds();
        $updatedOrderIds = $message->getUpdatedOrderIds();
        if (empty($newOrderIds) && empty($updatedOrderIds)) {
            return $result;
        }

        if (!empty($newOrderIds)) {
            $newOrderLookupIds = array_is_list($newOrderIds) ? $newOrderIds : array_keys($newOrderIds);
            foreach ($this->getOrders($context, $newOrderLookupIds) as $order) {
                try {
                    $sessionId = $newOrderIds[$order->getId()] ?? null;
                    $this->sendNewOrder($order, $account, $context, $sessionId);
                } catch (Throwable $e) {
                    $result->addError($e);
                }
            }
        }

        if (!empty($updatedOrderIds)) {
            $updatedOrderLookupIds = array_is_list($updatedOrderIds) ? $updatedOrderIds : array_values(
                $updatedOrderIds,
            );
            foreach ($this->getOrders($context, $updatedOrderLookupIds) as $order) {
                try {
                    $this->sendUpdatedOrder($order, $account);
                } catch (Throwable $e) {
                    $result->addError($e);
                }
            }
        }

        return $result;
    }

    private function getOrders(SalesChannelContext $salesChannelContext, array $orderIds): EntityCollection
    {
        $context = $salesChannelContext->getContext();
        $criteria = NostoCriteriaFactory::create();
        $criteria->addAssociation('stateMachineState');
        $criteria->addAssociation('orderCustomer');
        $criteria->addAssociation('currency');
        $criteria->addAssociation('addresses');
        $criteria->addAssociation('billingAddress');
        $criteria->addAssociation('transactions.paymentMethod');
        $criteria->addAssociation('lineItems.orderLineItem.product');
        $criteria->addFilter(new EqualsAnyFilter('id', $orderIds));
        $criteria->addFilter(new EqualsFilter('languageId', $context->getLanguageId()));
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelContext->getSalesChannelId()));
        $this->eventDispatcher->dispatch(new NostoOrderCriteriaEvent($criteria, $context));
        return $this->orderRepository->search($criteria, $context)->getEntities();
    }

    private function sendNewOrder(
        OrderEntity $order,
        Account $account,
        SalesChannelContext $context,
        ?string $sessionId,
    ): void {
        if (!$sessionId) {
            return;
        }
        $nostoOrder = $this->nostoOrderbuilder->build(
            $order,
            $context,
        );
        $nostoCustomerIdentifier = AbstractGraphQLOperation::IDENTIFIER_BY_CID;
        $operation = new OrderCreate(
            $nostoOrder,
            $account->getNostoAccount(),
            $nostoCustomerIdentifier,
            $sessionId,
        );
        $this->eventDispatcher->dispatch(new BeforeOrderCreatedEvent($operation, $context->getContext()));
        $operation->execute();
    }

    private function sendUpdatedOrder(OrderEntity $order, Account $account): void
    {
        $nostoOrderStatus = $this->nostoOrderStatusBuilder->build($order);
        $operation = new OrderStatus($account->getNostoAccount(), $nostoOrderStatus);
        $operation->execute();
    }
}
