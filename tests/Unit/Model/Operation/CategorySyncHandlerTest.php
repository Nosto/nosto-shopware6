<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Model\Operation;

use Nosto\Model\Category\Category as NostoCategory;
use Nosto\NostoIntegration\Async\CategorySyncMessage;
use Nosto\NostoIntegration\Model\Nosto\Account;
use Nosto\NostoIntegration\Model\Nosto\Account\KeyChain;
use Nosto\NostoIntegration\Model\Nosto\Account\Provider as AccountProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Category\Builder as CategoryBuilder;
use Nosto\NostoIntegration\Model\Operation\CategorySyncHandler;
use Nosto\Scheduler\Model\Job\Message\WarningMessage;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class CategorySyncHandlerTest extends TestCase
{
    public function testExecuteAggregatesIdenticalWarningsAcrossAccountContexts(): void
    {
        $context = Context::createDefaultContext();
        $categoryId = Uuid::randomHex();
        $category = new CategoryEntity();
        $category->setId($categoryId);
        $categories = new CategoryCollection([$category]);

        $categoryRepository = $this->createMock(EntityRepository::class);
        $categoryRepository->method('search')->willReturnCallback(
            static fn (Criteria $criteria, Context $searchContext): EntitySearchResult => new EntitySearchResult(
                CategoryDefinition::ENTITY_NAME,
                1,
                $categories,
                new AggregationResultCollection(),
                $criteria,
                $searchContext,
            ),
        );

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn($context);
        $contextFactory = $this->createMock(AbstractSalesChannelContextFactory::class);
        $contextFactory->method('create')->willReturn($salesChannelContext);

        $accountProvider = $this->createMock(AccountProvider::class);
        $accountProvider->method('all')->willReturn([
            new Account('channel-1', 'language-1', 'account-1', new KeyChain([])),
            new Account('channel-2', 'language-2', 'account-2', new KeyChain([])),
        ]);

        $nostoCategory = new NostoCategory();
        $nostoCategory->setId($categoryId);
        $nostoCategory->setTitle('Category without an SEO URL');
        $categoryBuilder = $this->createMock(CategoryBuilder::class);
        $categoryBuilder->method('build')->willReturn($nostoCategory);

        $handler = new CategorySyncHandler(
            $contextFactory,
            $categoryRepository,
            $accountProvider,
            $categoryBuilder,
            $this->createMock(EventDispatcherInterface::class),
        );

        $result = $handler->execute(new CategorySyncMessage(
            Uuid::randomHex(),
            Uuid::randomHex(),
            [$categoryId],
            $context,
        ));

        self::assertCount(1, $result->getMessages());
        self::assertInstanceOf(WarningMessage::class, $result->getMessages()[0]);
        self::assertSame(
            sprintf(
                'Category url is empty, Category urlPath is empty. Ignoring upsert for category with id %s Account contexts: channel-1/language-1, channel-2/language-2.',
                $categoryId,
            ),
            $result->getMessages()[0]->getMessage(),
        );
    }
}
