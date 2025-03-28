<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Utils;

use Doctrine\DBAL\Connection;
use Nosto\NostoIntegration\Model\Config\NostoConfigService;
use Nosto\NostoIntegration\Search\Request\Handler\SortHandlers\RecommendationSortingHandler;
use Shopware\Core\Content\Product\SalesChannel\Search\ResolvedCriteriaProductSearchRoute;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

class Lifecycle
{
    private Connection $connection;

    private EntityRepository $sortingRepository;

    private EntityRepository $salesChannelRepository;

    private EntityRepository $systemConfigRepository;

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly bool $hasOtherSchedulerDependency,
    ) {
        /** @var Connection $connection */
        $connection = $container->get(Connection::class);

        $this->sortingRepository = $container->get('product_sorting.repository');
        $this->connection = $connection;
        $this->salesChannelRepository = $this->container->get('sales_channel.repository');
        $this->systemConfigRepository = $this->container->get('system_config.repository');
    }

    public function install(InstallContext $installContext): void
    {
        $this->importSorting($installContext->getContext());
    }

    public function update(UpdateContext $updateContext): void
    {
        $this->importSorting($updateContext->getContext());
        if (version_compare($updateContext->getCurrentPluginVersion(), '1.0.10', '<')) {
            $this->removeOldTags($updateContext->getContext());
        }
    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
        if (!$sorting = $this->getNostoSorting($deactivateContext->getContext())) {
            return;
        }
        $this->updateSorting($deactivateContext->getContext(), $sorting, false);
        $this->updateDefaultSorting($deactivateContext->getContext(), $sorting->getKey());
    }

    public function activate(ActivateContext $activateContext): void
    {
        if (!$sorting = $this->getNostoSorting($activateContext->getContext())) {
            return;
        }
        $this->updateSorting($activateContext->getContext(), $sorting);
        $this->updateDefaultSorting($activateContext->getContext(), $sorting->getId());
    }

    public function removeSorting(Context $context): void
    {
        if (!$sorting = $this->getNostoSorting($context)) {
            return;
        }
        $this->sortingRepository->delete([[
            'id' => $sorting->getId(),
        ]], $context);
    }

    public function getNostoSorting(Context $context): ?ProductSortingEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('key', RecommendationSortingHandler::MERCHANDISING_SORTING_KEY));

        return $this->sortingRepository->search($criteria, $context)->first();
    }

    public function updateSorting(Context $context, ProductSortingEntity $sorting, bool $isActivate = true): void
    {
        $data = [
            'id' => $sorting->getId(),
            'active' => $isActivate,
            'updated_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        $this->sortingRepository->update([$data], $context);
    }

    private function updateDefaultSorting(Context $context, string $sortingKey): void
    {
        $defaultSortingKey = $this->getDefaultSortingKey($context, 'core.listing.defaultSorting');
        if ($sortingKey === $defaultSortingKey) {
            $this->setNewDefaultSorting($context, 'core.listing.defaultSorting', true);
        }

        $defaultSearchSortingKey = $this->getDefaultSortingKey($context, 'core.listing.defaultSearchResultSorting');
        if ($sortingKey === $defaultSearchSortingKey) {
            $this->setNewDefaultSorting($context, 'core.listing.defaultSearchResultSorting');
        }
    }

    private function setNewDefaultSorting(
        Context $context,
        string $configurationKey,
        bool $isCategorySorting = false,
    ): void {
        $topResultSortingKey = $this->getTopResultsSortingKey($context);
        if ($isCategorySorting || !$topResultSortingKey) {
            $defaultSortingKey = $this->getHighPrioritySortingKey($context);
        } else {
            $defaultSortingKey = $topResultSortingKey;
        }
        $this->connection->update(
            'system_config',
            [
                'configuration_value' => '{"_value": "' . $defaultSortingKey . '"}',
                'updated_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            [
                'configuration_key' => $configurationKey,
            ],
        );
    }

    public function getDefaultSortingKey(Context $context, string $key = null): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('configurationKey', $key));
        $sorting = $this->systemConfigRepository->search($criteria, $context)->first();

        return $sorting?->getConfigurationValue();
    }

    public function getHighPrioritySortingKey(Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [
            new EqualsFilter('key', RecommendationSortingHandler::MERCHANDISING_SORTING_KEY),
            new EqualsFilter('key', ResolvedCriteriaProductSearchRoute::DEFAULT_SEARCH_SORT),
        ]));
        $criteria->addFilter(new EqualsFilter('active', 1));
        $criteria->addFilter(new EqualsFilter('locked', 0));
        $criteria->addSorting(new FieldSorting('priority', FieldSorting::DESCENDING));
        $criteria->setLimit(1);
        $sorting = $this->sortingRepository->search($criteria, $context)->first();

        return $sorting?->getKey();
    }

    public function getTopResultsSortingKey(Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('key', ResolvedCriteriaProductSearchRoute::DEFAULT_SEARCH_SORT));
        $sorting = $this->sortingRepository->search($criteria, $context)->first();

        return $sorting?->getKey();
    }

    public function importSorting(Context $context): void
    {
        $sorting = $this->getNostoSorting($context);

        if ($sorting) {
            $data = [
                'id' => $sorting->getId(),
                'active' => true,
                'fields' => [],
            ];
        } else {
            $data = [
                'key' => RecommendationSortingHandler::MERCHANDISING_SORTING_KEY,
                'priority' => 0,
                'active' => true,
                'fields' => [],
                'label' => 'Recommendation',
                'locked' => false,
            ];
        }

        $this->sortingRepository->upsert([$data], $context);
    }

    public function uninstall(UninstallContext $context): void
    {
        if ($sorting = $this->getNostoSorting($context->getContext())) {
            $this->removeSorting($context->getContext());
            $this->updateDefaultSorting($context->getContext(), $sorting->getId());
        }
        if ($this->hasOtherSchedulerDependency) {
            $this->removePendingJobs();
        } else {
            // TODO: OdScheduler must be responsible for its uninstallation - move such operations to it in future.
            $this->connection->executeStatement('DROP TABLE IF EXISTS `nosto_scheduler_job_message`');
            $this->connection->executeStatement('DROP TABLE IF EXISTS `nosto_scheduler_job`');

            $schedulerMigrationClassWildcard = addcslashes('Nosto\Scheduler\Migration', '\\_%') . '%';
            $this->connection->executeUpdate(
                'DELETE FROM migration WHERE class LIKE :class',
                [
                    'class' => $schedulerMigrationClassWildcard,
                ],
            );
        }

        if (!$context->keepUserData()) {
            $this->removeTables();
        }
    }

    public function removePendingJobs(): void
    {
        $this->connection->executeStatement(
            "DELETE from `nosto_scheduler_job` WHERE `type` LIKE :prefix",
            [
                'prefix' => 'nosto-integration%',
            ],
        );
    }

    public function removeOldTags(Context $context): void
    {
        $channelCriteria = new Criteria();
        $channelCriteria->addFilter(
            new EqualsAnyFilter('typeId', [Defaults::SALES_CHANNEL_TYPE_STOREFRONT, Defaults::SALES_CHANNEL_TYPE_API]),
        );
        $channelIds = $this->salesChannelRepository->searchIds($channelCriteria, $context);

        foreach ($channelIds->getIds() as $channelId) {
            $this->removeOldTagsForChannel($channelId);
        }

        $this->removeOldTagsForChannel();
    }

    protected function removeOldTagsForChannel(?string $channelId = null): void
    {
        $configService = $this->container->get(NostoConfigService::class);

        for ($i = 1; $i < 4; ++$i) {
            $configService->delete(NostoConfigService::TAG_FIELD_TEMPLATE . $i, $channelId);
        }
    }

    public function removeTables(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `nosto_integration_checkout_mapping`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `nosto_integration_entity_changelog`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `nosto_integration_config`');
    }
}
