<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests;

use Nosto\NostoIntegration\NostoIntegration;
use Nosto\NostoIntegration\Utils\MigrationHelper;
use Nosto\Scheduler\NostoScheduler;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Util\AssetService;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

echo "Test autoloaded\n";

class NostoIntegrationTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testNostoSchedulerIsAddedOnActivation(): void
    {
        /** @var NostoIntegration $nostoIntegration */
        $nostoIntegration = $this->getContainer()->get(NostoIntegration::class);

        $migrationHelper = $this->getMockBuilder(MigrationHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $migrationHelper->expects($this->once())
            ->method('getMigrationCollection')
            ->with($this->isInstanceOf(NostoScheduler::class));
        $this->getContainer()->set(MigrationHelper::class, $migrationHelper);

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