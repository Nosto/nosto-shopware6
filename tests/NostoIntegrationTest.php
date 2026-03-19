<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests;

use Nosto\NostoIntegration\NostoIntegration;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Plugin\Util\AssetService;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class NostoIntegrationTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testNostoSchedulerIsAddedOnActivation(): void
    {
        $this->mockDependencyMigrationExecution();

        /** @var NostoIntegration $nostoIntegration */
        $nostoIntegration = $this->getContainer()->get(NostoIntegration::class);

        $assetService = $this->getMockBuilder(AssetService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $assetService->expects($this->once())
            ->method('copyAssetsFromBundle')
            ->with('NostoScheduler');
        $this->getContainer()->set('nosto.plugin.assetservice.public', $assetService);

        $activateContext = $this->getMockBuilder(ActivateContext::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getContext'])
            ->getMock();
        $activateContext->method('getContext')->willReturn(Context::createDefaultContext());

        $nostoIntegration->activate($activateContext);
    }

    public function testNostoSchedulerMigrationsRunOnInstall(): void
    {
        $this->mockDependencyMigrationExecution();

        /** @var NostoIntegration $nostoIntegration */
        $nostoIntegration = $this->getContainer()->get(NostoIntegration::class);

        $installContext = $this->getMockBuilder(InstallContext::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getContext'])
            ->getMock();
        $installContext->method('getContext')->willReturn(Context::createDefaultContext());

        $nostoIntegration->install($installContext);
    }

    public function testNostoSchedulerMigrationsRunOnUpdate(): void
    {
        $this->mockDependencyMigrationExecution();

        /** @var NostoIntegration $nostoIntegration */
        $nostoIntegration = $this->getContainer()->get(NostoIntegration::class);

        $updateContext = $this->getMockBuilder(UpdateContext::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getContext', 'getCurrentPluginVersion'])
            ->getMock();
        $updateContext->method('getContext')->willReturn(Context::createDefaultContext());
        $updateContext->method('getCurrentPluginVersion')->willReturn('5.2.9');

        $nostoIntegration->update($updateContext);
    }

    private function mockDependencyMigrationExecution(): void
    {
        $migrationCollection = $this->getMockBuilder(MigrationCollection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $migrationCollection->expects($this->once())
            ->method('sync');
        $migrationCollection->expects($this->once())
            ->method('migrateInPlace');

        $migrationLoader = $this->getMockBuilder(MigrationCollectionLoader::class)
            ->disableOriginalConstructor()
            ->getMock();
        $migrationLoader->expects($this->once())
            ->method('addSource');
        $migrationLoader->expects($this->once())
            ->method('collect')
            ->with('NostoScheduler')
            ->willReturn($migrationCollection);
        $this->getContainer()->set(MigrationCollectionLoader::class, $migrationLoader);
    }
}
