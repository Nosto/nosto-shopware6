<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Decorator\Core;

use Nosto\NostoIntegration\Decorator\Core\Content\Product\SalesChannel\Listing\ProductListingRoute;
use Nosto\NostoIntegration\Enums\ProductIdentifierOptions;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Account;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

final class ProductListingRouteImpressionTest extends TestCase
{
    public function testMissingAccountDoesNotBreakCategoryPage(): void
    {
        // App token is inherited from the default config, but no account exists for this language.
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with(self::stringContains('No Nosto account found'));

        $this->sendImpression('app-token', null, $logger);
    }

    public function testIncompleteTokensDoNotBreakCategoryPage(): void
    {
        // Real account provider: the language is enabled and has an app token, but the product and email tokens
        // are empty. Token validation fails, the provider logs and skips it, so no account exists for the language.
        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('isAccountEnabled')->willReturn(true);
        $configProvider->method('getAccountName')->willReturn('account-name');
        $configProvider->method('getAppToken')->willReturn(str_repeat('a', 64));
        $configProvider->method('getProductToken')->willReturn('');
        $configProvider->method('getEmailToken')->willReturn('');
        $configProvider->method('isEnabledMultiCurrency')->willReturn(false);

        $language = new LanguageEntity();
        $language->setId('language-id');
        $channel = new SalesChannelEntity();
        $channel->setId('sales-channel-id');
        $channel->setLanguages(new LanguageCollection([$language]));

        $channelRepo = $this->createMock(EntityRepository::class);
        $channelRepo->method('search')->willReturn(new EntitySearchResult(
            'sales_channel',
            1,
            new SalesChannelCollection([$channel]),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        ));

        $accountProvider = new Account\Provider($configProvider, $channelRepo, $this->createMock(LoggerInterface::class));
        self::assertNull($accountProvider->get(Context::createDefaultContext(), 'sales-channel-id', 'language-id'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with(self::stringContains('No Nosto account found'));

        $this->sendImpression(str_repeat('a', 64), $accountProvider->get(Context::createDefaultContext(), 'sales-channel-id', 'language-id'), $logger);
    }

    public function testMissingAppTokenDoesNotBreakCategoryPage(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with(self::stringContains('No app token found'));

        $this->sendImpression('', null, $logger);
    }

    private function sendImpression(string $appToken, ?Account $account, LoggerInterface $logger): void
    {
        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('getAccountId')->willReturn('account-id');
        $configProvider->method('getAppToken')->willReturn($appToken);
        $configProvider->method('getProductIdentifier')->willReturn(ProductIdentifierOptions::PRODUCT_ID);

        $accountProvider = $this->createMock(Account\Provider::class);
        $accountProvider->method('get')->willReturn($account);

        // Only these three dependencies are used by sendImpressionAnalytics.
        $reflection = new \ReflectionClass(ProductListingRoute::class);
        $route = $reflection->newInstanceWithoutConstructor();
        foreach (['configProvider' => $configProvider, 'logger' => $logger, 'accountProvider' => $accountProvider] as $name => $dependency) {
            $reflection->getProperty($name)->setValue($route, $dependency);
        }

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getContext')->willReturn(Context::createDefaultContext());
        $context->method('getSalesChannelId')->willReturn('sales-channel-id');
        $context->method('getLanguageId')->willReturn('language-id');

        $request = Request::create('/category');
        $request->cookies->set('2c_cId', 'session-id');

        $listing = new ProductListingResult('product', 0, new ProductCollection(), null, new Criteria(), Context::createDefaultContext());

        $category = new CategoryEntity();
        $category->setSeoUrls(new SeoUrlCollection());

        $method = new \ReflectionMethod($route, 'sendImpressionAnalytics');
        $method->invoke($route, $context, $listing, $category, $request);
    }
}
