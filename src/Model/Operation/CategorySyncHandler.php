<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Operation;

use Nosto\Model\Category\Category as NostoCategory;
use Nosto\NostoIntegration\Async\CategorySyncMessage;
use Nosto\NostoIntegration\Model\Nosto\Account;
use Nosto\NostoIntegration\Model\Nosto\Entity\Category\Builder as CategoryBuilder;
use Nosto\NostoIntegration\Model\Nosto\Entity\Category\Event\NostoCategoryCriteriaEvent;
use Nosto\NostoIntegration\Model\Operation\Event\BeforeCategoryUpdateEvent;
use Nosto\Operation\Category\CategoryUpdate;
use Nosto\Scheduler\Model\Job\Message\WarningMessage;
use Nosto\Scheduler\Model\Job\{JobHandlerInterface, JobResult};
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\{EntityCollection, EntityRepository};
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;

class CategorySyncHandler implements JobHandlerInterface
{
    public const HANDLER_CODE = 'nosto-integration-category-sync';

    public function __construct(
        private readonly AbstractSalesChannelContextFactory $channelContextFactory,
        private readonly EntityRepository $categoryRepository,
        private readonly Account\Provider $accountProvider,
        private readonly CategoryBuilder $nostoCategoryBuilder,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @param CategorySyncMessage $message
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
            foreach ($accountOperationResult->getMessages() as $error) {
                $operationResult->addMessage($error);
            }
        }

        return $operationResult;
    }

    private function doOperation(Account $account, SalesChannelContext $context, object $message): JobResult
    {
        $result = new JobResult();
        foreach ($this->getCategories($context->getContext(), $message->getCategoryIds()) as $category) {
            try {
                $this->sendCategory($category, $account, $context, $result);
            } catch (Throwable $e) {
                $result->addError($e);
            }
        }

        return $result;
    }

    private function getCategories(Context $context, array $categoryIds): EntityCollection
    {
        $criteria = new Criteria();
        $criteria->getAssociation('seoUrls');
        $criteria->getAssociation('translations');
        $criteria->addFilter(new EqualsAnyFilter('id', $categoryIds));
        $this->eventDispatcher->dispatch(new NostoCategoryCriteriaEvent($criteria, $context));
        return $this->categoryRepository->search($criteria, $context)->getEntities();
    }

    private function sendCategory(
        CategoryEntity $category,
        Account $account,
        SalesChannelContext $context,
        JobResult $result,
    ): void {
        $nostoCategory = $this->nostoCategoryBuilder->build(
            $category,
            $context,
        );
        $invalidMessage = $this->validateCategory(
            $nostoCategory->getId(),
            $nostoCategory,
        );

        if ($invalidMessage) {
            $result->addMessage($invalidMessage);
            return;
        }

        $operation = new CategoryUpdate(
            $nostoCategory,
            $account->getNostoAccount(),
        );
        $this->eventDispatcher->dispatch(new BeforeCategoryUpdateEvent($operation, $context->getContext()));
        $operation->execute();
    }

    private function validateCategory(string $categoryId, NostoCategory $category): ?WarningMessage
    {
        $message = '';

        if (!$category->getTitle()) {
            $message .= 'Category name is empty, ';
        }

        return empty($message) ? null : new WarningMessage(
            $message . 'ignoring upsert for category with id. ' . $categoryId,
        );
    }
}
