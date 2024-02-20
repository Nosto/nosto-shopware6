<?php

namespace Nosto\NostoIntegration\Tests\Traits\DataHelpers;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

trait SalesChannelHelper
{
    public function buildSalesChannelContext(
        string $salesChannelId = Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
        string $languageId = Defaults::LANGUAGE_SYSTEM,
    ): SalesChannelContext {
        /** @var SalesChannelContextFactory $salesChannelContextFactory */
        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);

        return $salesChannelContextFactory->create(
            Uuid::randomHex(),
            $salesChannelId,
            $this->buildSalesChannelContextFactoryOptions($languageId)
        );
    }

    public function buildAndCreateSalesChannelContext(
        string $salesChannelId = Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
        string $url = 'http://test.uk',
        string $languageId = Defaults::LANGUAGE_SYSTEM,
        array $overrides = [],
        string $currencyId = Defaults::CURRENCY
    ): SalesChannelContext {
        $this->upsertSalesChannel($salesChannelId, $url, $languageId, $overrides, $currencyId);

        return $this->buildSalesChannelContext($salesChannelId, $languageId);
    }

    public function upsertSalesChannel(
        string $salesChannelId = Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
        string $url = 'http://test.uk',
        string $languageId = Defaults::LANGUAGE_SYSTEM,
        array $overrides = [],
        string $currencyId = Defaults::CURRENCY
    ): void {
        $locale = $this->getLocaleOfLanguage($languageId);

        if ($locale) {
            $snippetSet = $this->getSnippetSetIdForLocale($locale);
        } else {
            $snippetSet = $this->fetchIdFromDatabase('snippet_set');
        }

        $countryId = $this->getContainer()->get('country.repository')->searchIds(
            new Criteria(),
            Context::createDefaultContext()
        )->firstId();

        $paymentMethodId = $this->getContainer()->get('payment_method.repository')->searchIds(
            new Criteria(),
            Context::createDefaultContext()
        )->firstId();

        $shippingMethodId = $this->getContainer()->get('shipping_method.repository')->searchIds(
            new Criteria(),
            Context::createDefaultContext()
        )->firstId();

        $customerGroupId = $this->getContainer()->get('customer_group.repository')->searchIds(
            new Criteria(),
            Context::createDefaultContext()
        )->firstId();

        $catCriteria = new Criteria();
        $catCriteria->addFilter(
            new EqualsFilter('parentId', null)
        );
        $navigationCategoryId = $this->getContainer()->get('category.repository')->searchIds(
            $catCriteria,
            Context::createDefaultContext()
        )->firstId();

        $salesChannel = array_merge([
            'id' => $salesChannelId,
            'languageId' => $languageId,
            'customerGroupId' => $customerGroupId,
            'currencyId' => $currencyId,
            'paymentMethodId' => $paymentMethodId,
            'shippingMethodId' => $shippingMethodId,
            'countryId' => $countryId,
            'navigationCategoryId' => $navigationCategoryId,
            'accessKey' => 'KEY',
            'domains' => [
                [
                    'url' => $url,
                    'currencyId' => $currencyId,
                    'languageId' => $languageId,
                    'snippetSetId' => $snippetSet
                ]
            ],
            'typeId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
            'translations' => [
                $languageId => [
                    'name' => 'Storefront'
                ]
            ],
            'languages' => [
                ['id' => $languageId]
            ]
        ], $overrides);

        /** @var EntityRepository $salesChannelRepository */
        $salesChannelRepository = $this->getContainer()->get('sales_channel.repository');
        $salesChannelExists = $salesChannelRepository
            ->searchIds(new Criteria([$salesChannelId]), Context::createDefaultContext())
            ->firstId();

        if ($languageId !== Defaults::LANGUAGE_SYSTEM) {
            $salesChannel['translations'][Defaults::LANGUAGE_SYSTEM] = ['name' => 'Storefront Default'];
        }

        if ($salesChannelExists && $languageId === Defaults::LANGUAGE_SYSTEM) {
            unset($salesChannel['languages']);
        }

        $salesChannelRepository->upsert(
            [$salesChannel],
            Context::createDefaultContext()
        );
    }

    private function buildSalesChannelContextFactoryOptions(?string $languageId): array {
        $options = [];

        if ($languageId) {
            $options[SalesChannelContextService::LANGUAGE_ID] = $languageId;
        }

        return $options;
    }

    /**
     * In order to create a useable sales channel context we need to pass some IDs for initialization from several
     * tables from the database.
     */
    private function fetchIdFromDatabase(string $table): string
    {
        return $this->getContainer()->get(Connection::class)->fetchOne('SELECT LOWER(HEX(id)) FROM ' . $table);
    }

    private function getLocaleIdOfLanguage(string $languageId = Defaults::LANGUAGE_SYSTEM): string
    {
        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get('language.repository');

        /** @var LanguageEntity $language */
        $language = $repository->search(new Criteria([$languageId]), Context::createDefaultContext())->get($languageId);

        return $language->getLocaleId();
    }

    private function getLocaleOfLanguage(string $languageId = Defaults::LANGUAGE_SYSTEM): ?string
    {
        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get('language.repository');

        $criteria = new Criteria([$languageId]);
        $criteria->addAssociation('translationCode');

        /** @var LanguageEntity $language */
        $language = $repository->search($criteria, Context::createDefaultContext())->get($languageId);

        return $language->getTranslationCode()?->getCode();
    }
}
