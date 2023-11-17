<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Utils;

use Doctrine\DBAL\Connection;
use Nosto\NostoIntegration\Model\Config\NostoConfigService;
use Nosto\NostoIntegration\Service\CategoryMerchandising\MerchandisingSearchApi;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Symfony\Component\DependencyInjection\ContainerInterface;
use function version_compare;

class Lifecycle
{
    private Connection $connection;

    private ContainerInterface $container;

    private bool $hasOtherSchedulerDependency;

    private EntityRepository $sortingRepository;

    private NostoConfigService $nostoConfigService;

    private EntityRepository $salesChannelRepository;

    public function __construct(
        ContainerInterface $container,
        bool $hasOtherSchedulerDependency,
    ) {
        /** @var Connection $connection */
        $connection = $container->get(Connection::class);

        $this->container = $container;
        $this->hasOtherSchedulerDependency = $hasOtherSchedulerDependency;

        $this->sortingRepository = $container->get('product_sorting.repository');
        $this->connection = $connection;
        $this->nostoConfigService = $this->container->get(NostoConfigService::class);
        $this->salesChannelRepository = $this->container->get('sales_channel.repository');
    }

    public function install(InstallContext $installContext)
    {
        $this->importSorting($installContext->getContext());
    }

    public function update(UpdateContext $updateContext)
    {
        $this->importSorting($updateContext->getContext());
        if (version_compare($updateContext->getCurrentPluginVersion(), '1.0.10', '<')) {
            $this->removeOldTags($updateContext->getContext());
        }
    }

    public function deactivate(DeactivateContext $deactivateContext)
    {
        $this->removeSorting($deactivateContext->getContext());
    }

    public function activate(ActivateContext $activateContext)
    {
        $this->importSorting($activateContext->getContext());
    }

    public function removeSorting(Context $context)
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('key', MerchandisingSearchApi::MERCHANDISING_SORTING_KEY));
        $sorting = $this->sortingRepository->search($criteria, $context)->first();
        if ($sorting == null) {
            return;
        }
        $this->sortingRepository->delete([[
            'id' => $sorting->getId(),
        ]], $context);
    }

    public function importSorting(Context $context)
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('key', MerchandisingSearchApi::MERCHANDISING_SORTING_KEY));
        $sorting = $this->sortingRepository->search($criteria, $context);

        if ($sorting->count() > 0) {
            $data = [
                'id' => $sorting->first()->getId(),
                'fields' => [[
                    "field" => "product.name",
                    "order" => "desc",
                    "priority" => 1,
                    "naturalSorting" => 0,
                ]],
            ];
        } else {
            $data = [
                'key' => MerchandisingSearchApi::MERCHANDISING_SORTING_KEY,
                'priority' => 0,
                'active' => true,
                'fields' => [[
                    "field" => "product.name",
                    "order" => "desc",
                    "priority" => 1,
                    "naturalSorting" => 0,
                ]],
                'label' => 'Recommendation',
                'locked' => false,
            ];
        }

        $this->sortingRepository->upsert([$data], $context);
    }

    public function uninstall(UninstallContext $context): void
    {
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

    public function removePendingJobs()
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
        for ($i = 1; $i < 4; ++$i) {
            $this->nostoConfigService->delete(NostoConfigService::TAG_FIELD_TEMPLATE . $i, $channelId);
        }
    }

    public function removeTables(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `nosto_integration_checkout_mapping`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `nosto_integration_entity_changelog`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `nosto_integration_config`');
    }
}
