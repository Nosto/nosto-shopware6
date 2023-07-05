<?php declare(strict_types=1);

namespace Od\NostoIntegration\Utils;

use Doctrine\DBAL\Connection;
use Od\NostoIntegration\Model\ConfigProvider;
use Od\NostoIntegration\Service\CategoryMerchandising\MerchandisingSearchApi;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use function version_compare;

class Lifecycle
{
    private EntityRepository $systemConfigRepository;
    private Connection $connection;
    private ContainerInterface $container;
    private bool $hasOtherSchedulerDependency;
    private EntityRepository $sortingRepository;
    private SystemConfigService $systemConfigService;
    private EntityRepository $salesChannelRepository;

    public function __construct(
        ContainerInterface $container,
        bool $hasOtherSchedulerDependency
    ) {
        /** @var EntityRepository $systemConfigRepository */
        $systemConfigRepository = $container->get('system_config.repository');
        /** @var Connection $connection */
        $connection = $container->get(Connection::class);

        $this->container = $container;
        $this->hasOtherSchedulerDependency = $hasOtherSchedulerDependency;

        $this->systemConfigRepository = $systemConfigRepository;
        $this->sortingRepository = $container->get('product_sorting.repository');
        $this->connection = $connection;
        $this->systemConfigService = $this->container->get(SystemConfigService::class);
        $this->salesChannelRepository = $this->container->get('sales_channel.repository');
    }

    public function install(InstallContext $installContext) {
        $this->importSorting($installContext->getContext());
    }

    public function update(UpdateContext $updateContext) {
        $this->importSorting($updateContext->getContext());
        if (version_compare($updateContext->getCurrentPluginVersion(), '1.0.10', '<')) {
            $this->removeOldTags($updateContext->getContext());
        }
    }

    public function deactivate(DeactivateContext $deactivateContext) {
        $this->removeSorting($deactivateContext->getContext());
    }

    public function activate(ActivateContext $activateContext) {
        $this->importSorting($activateContext->getContext());
    }

    public function removeSorting(Context $context) {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('key', MerchandisingSearchApi::MERCHANDISING_SORTING_KEY));
        $sorting = $this->sortingRepository->search($criteria, $context)->first();
        if ($sorting == null) {
            return;
        }
        $this->sortingRepository->delete([['id' => $sorting->getId()]], $context);
    }

    public function importSorting(Context $context)
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('key', MerchandisingSearchApi::MERCHANDISING_SORTING_KEY));
        $sorting = $this->sortingRepository->search($criteria, $context);
        if ($sorting->count() > 0) {
            return;
        }
        $this->sortingRepository->upsert([
            [
                'key' => MerchandisingSearchApi::MERCHANDISING_SORTING_KEY,
                'priority' => 0,
                'active' => true,
                'fields' => [],
                'label' => 'Recommendation',
                'locked' => false,
            ],
        ], $context);
    }

    public function uninstall(UninstallContext $context): void
    {
        if ($this->hasOtherSchedulerDependency) {
            $this->removePendingJobs();
        } else {
            // TODO: OdScheduler must be responsible for its uninstallation - move such operations to it in future.
            $this->connection->executeStatement('DROP TABLE IF EXISTS `od_scheduler_job_message`');
            $this->connection->executeStatement('DROP TABLE IF EXISTS `od_scheduler_job`');

            $schedulerMigrationClassWildcard = addcslashes('Od\Scheduler\Migration', '\\_%') . '%';
            $this->connection->executeUpdate(
                'DELETE FROM migration WHERE class LIKE :class',
                ['class' => $schedulerMigrationClassWildcard]
            );
        }

        $this->removeConfigs($context->getContext());
        $this->removeTables();
    }

    public function removePendingJobs()
    {
        $this->connection->executeStatement(
            "DELETE from `od_scheduler_job` WHERE `type` LIKE :prefix",
            [
                'prefix' => 'od-nosto%',
            ],
        );
    }

    public function removeConfigs(Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new ContainsFilter('configurationKey', 'overdose_nosto'));
        $configIds = $this->systemConfigRepository->searchIds($criteria, $context)->getIds();
        $configIds = \array_map(static function ($id) {
            return ['id' => $id];
        }, $configIds);

        if (!empty($configIds)) {
            $this->systemConfigRepository->delete(array_values($configIds), $context);
        }
    }

    public function removeOldTags(Context $context): void
    {
        $channelCriteria = new Criteria();
        $channelCriteria->addFilter(new EqualsAnyFilter('typeId', [Defaults::SALES_CHANNEL_TYPE_STOREFRONT, Defaults::SALES_CHANNEL_TYPE_API]));
        $channelIds = $this->salesChannelRepository->searchIds($channelCriteria, $context);

        foreach ($channelIds->getIds() as $channelId) {
            $this->removeOldTagsForChannel($channelId);
        }

        $this->removeOldTagsForChannel();
    }

    protected function removeOldTagsForChannel(?string $channelId = null): void
    {
        for ($i = 1; $i < 4; ++$i) {
            $this->systemConfigService->delete('overdose_nosto.' . ConfigProvider::TAG_FIELD_TEMPLATE . $i, $channelId);
        }
    }

    public function removeTables(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `od_nosto_entity_changelog`');
    }
}
