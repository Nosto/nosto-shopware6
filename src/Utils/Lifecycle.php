<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Utils;

use Doctrine\DBAL\Connection;
use Nosto\NostoIntegration\Model\Config\NostoConfigService;
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

class Lifecycle
{
    private Connection $connection;

    private EntityRepository $sortingRepository;

    private EntityRepository $salesChannelRepository;

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly bool $hasOtherSchedulerDependency,
    ) {
        /** @var Connection $connection */
        $connection = $container->get(Connection::class);

        $this->sortingRepository = $container->get('product_sorting.repository');
        $this->connection = $connection;
        $this->salesChannelRepository = $this->container->get('sales_channel.repository');
    }

    public function install(InstallContext $installContext): void
    {
        $this->removeSorting($installContext->getContext());
    }

    public function update(UpdateContext $updateContext): void
    {
        $this->removeSorting($updateContext->getContext());
        if (version_compare($updateContext->getCurrentPluginVersion(), '1.0.10', '<')) {
            $this->removeOldTags($updateContext->getContext());
        }
    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
        $this->removeSorting($deactivateContext->getContext());
    }

    public function activate(ActivateContext $activateContext): void
    {
        $this->removeSorting($activateContext->getContext());
    }

    /**
     * The sorting was remove with v3, so only the remove sorting is called without it being added.
     */
    public function removeSorting(Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('key', 'nosto-recommendation'));
        $sorting = $this->sortingRepository->search($criteria, $context)->first();
        if ($sorting == null) {
            return;
        }
        $this->sortingRepository->delete([[
            'id' => $sorting->getId(),
        ]], $context);
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
