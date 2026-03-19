<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests;

use Nosto\NostoIntegration\NostoIntegration;
use Nosto\NostoIntegration\Utils\MigrationHelper;
use Nosto\Scheduler\NostoScheduler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Plugin\Util\AssetService;

class NostoIntegrationTest extends TestCase
{
    public function testNostoSchedulerMigrationsRunOnInstall(): void
    {
        $plugin = $this->createPluginMock();

        $installContext = $this->getMockBuilder(InstallContext::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getContext'])
            ->getMock();
        $installContext->method('getContext')->willReturn(Context::createDefaultContext());

        $plugin->install($installContext);
    }

    public function testNostoSchedulerMigrationsRunOnUpdate(): void
    {
        $plugin = $this->createPluginMock();

        $updateContext = $this->getMockBuilder(UpdateContext::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getContext', 'getCurrentPluginVersion'])
            ->getMock();
        $updateContext->method('getContext')->willReturn(Context::createDefaultContext());
        $updateContext->method('getCurrentPluginVersion')->willReturn('5.2.9');

        $plugin->update($updateContext);
    }

    public function testDependencyBundleAssetsAreCopiedOnActivation(): void
    {
        $plugin = $this->createPluginMock(false);

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

        $plugin->activate($activateContext);
    }

    private function createPluginMock(bool $mockAssetCopy = true): NostoIntegration
    {
        $migrationCollection = $this->getMockBuilder(MigrationCollection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $migrationCollection->expects($this->once())
            ->method('migrateInPlace');

        $migrationHelper = $this->getMockBuilder(MigrationHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $migrationHelper->expects($this->once())
            ->method('getMigrationCollection')
            ->with($this->isInstanceOf(NostoScheduler::class))
            ->willReturn($migrationCollection);

        /** @var NostoIntegration&\PHPUnit\Framework\MockObject\MockObject $plugin */
        $plugin = $this->getMockBuilder(NostoIntegration::class)
            ->setConstructorArgs([
                true,
                'custom/plugins/nosto-shopware6',
                $this->getContainer()->getParameter('kernel.project_dir'),
            ])
            ->onlyMethods(
                $mockAssetCopy ? ['createMigrationHelper', 'copyDependencyBundleAssets'] : ['createMigrationHelper'],
            )
            ->getMock();

        $plugin->expects($this->once())
            ->method('createMigrationHelper')
            ->willReturn($migrationHelper);

        if ($mockAssetCopy) {
            $plugin->expects($this->never())
                ->method('copyDependencyBundleAssets');
        }

        $plugin->setContainer($this->getContainer());

        return $plugin;
    }
}
