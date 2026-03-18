<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests;

use Nosto\NostoIntegration\NostoIntegration;
use Nosto\Scheduler\NostoScheduler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Util\AssetService;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class NostoIntegrationTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testNostoSchedulerIsAddedOnActivation(): void
    {
        /** @var NostoIntegration $nostoIntegration */
        $nostoIntegration = $this->getContainer()->get(NostoIntegration::class);

        $migrationCollection = $this->getMockBuilder(MigrationCollection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $migrationCollection->expects($this->once())
            ->method('sync');

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
}
