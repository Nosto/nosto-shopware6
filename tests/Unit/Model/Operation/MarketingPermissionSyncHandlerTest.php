<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Model\Operation;

use Nosto\NostoIntegration\Async\MarketingPermissionSyncMessage;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Account;
use Nosto\NostoIntegration\Model\Nosto\Account\KeyChain;
use Nosto\NostoIntegration\Model\Nosto\Account\Provider as AccountProvider;
use Nosto\NostoIntegration\Model\Operation\MarketingPermissionSyncHandler;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientCollection;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class MarketingPermissionSyncHandlerTest extends TestCase
{
    public function testSubscribersAreFetchedWhenCustomerDataToNostoEnabled(): void
    {
        $context = Context::createDefaultContext();

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('search')
            ->willReturnCallback(
                static fn (Criteria $criteria, Context $ctx): EntitySearchResult => self::emptyResult($criteria, $ctx),
            );

        $handler = $this->createHandler($context, $repository, customerDataEnabled: true);

        $result = $handler->execute($this->createMessage($context));

        self::assertFalse($result->hasErrors());
    }

    public function testSubscribersAreNotFetchedWhenCustomerDataToNostoDisabled(): void
    {
        $context = Context::createDefaultContext();

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->never())->method('search');

        $handler = $this->createHandler($context, $repository, customerDataEnabled: false);

        $result = $handler->execute($this->createMessage($context));

        self::assertFalse($result->hasErrors());
    }

    private function createHandler(
        Context $context,
        EntityRepository $repository,
        bool $customerDataEnabled,
    ): MarketingPermissionSyncHandler {
        $account = new Account(Uuid::randomHex(), $context->getLanguageId(), 'account', new KeyChain([]));

        $accountProvider = $this->createMock(AccountProvider::class);
        $accountProvider->method('all')->willReturn([$account]);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);

        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('shouldSendCustomerDataFromBackend')->willReturn($customerDataEnabled);

        return new MarketingPermissionSyncHandler(
            $repository,
            $accountProvider,
            $eventDispatcher,
            $configProvider,
        );
    }

    private function createMessage(Context $context): MarketingPermissionSyncMessage
    {
        return new MarketingPermissionSyncMessage(
            Uuid::randomHex(),
            Uuid::randomHex(),
            [Uuid::randomHex()],
            $context,
        );
    }

    private static function emptyResult(Criteria $criteria, Context $context): EntitySearchResult
    {
        return new EntitySearchResult(
            NewsletterRecipientEntity::class,
            0,
            new NewsletterRecipientCollection([]),
            new AggregationResultCollection(),
            $criteria,
            $context,
        );
    }
}
