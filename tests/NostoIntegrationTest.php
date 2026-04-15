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
use Symfony\Component\DependencyInjection\ContainerInterface;

class NostoIntegrationTest extends TestCase
{
    private MutableContainerProxy $containerProxy;

    public function getName(bool $withDataSet = true): string
    {
        if (method_exists($this, 'name')) {
            return $this->name();
        }

        return parent::getName($withDataSet);
    }

    public function testNostoSchedulerMigrationsRunOnInstall(): void
    {
        $this->disableEventDispatcherListeners();
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
        $this->disableEventDispatcherListeners();
        $plugin = $this->createPluginMock();

        $updateContext = $this->getMockBuilder(UpdateContext::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getContext', 'getCurrentPluginVersion'])
            ->getMock();
        $updateContext->method('getContext')->willReturn(Context::createDefaultContext());
        $updateContext->method('getCurrentPluginVersion')->willReturn('1.0.10');

        $plugin->update($updateContext);
    }

    public function testDependencyBundleAssetsAreCopiedOnActivation(): void
    {
        $this->disableEventDispatcherListeners();
        $plugin = $this->createPluginMock();

        $assetService = $this->getMockBuilder(AssetService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $assetService->expects($this->once())
            ->method('copyAssetsFromBundle')
            ->with('NostoScheduler');
        $this->containerProxy->set('nosto.plugin.assetservice.public', $assetService);

        $activateContext = $this->getMockBuilder(ActivateContext::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getContext'])
            ->getMock();
        $activateContext->method('getContext')->willReturn(Context::createDefaultContext());

        $plugin->activate($activateContext);
    }

    private function createPluginMock(): NostoIntegration
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

        $this->containerProxy = new MutableContainerProxy($this->getContainer());

        /** @var NostoIntegration&\PHPUnit\Framework\MockObject\MockObject $plugin */
        $plugin = $this->getMockBuilder(NostoIntegration::class)
            ->setConstructorArgs([
                true,
                'custom/plugins/nosto-shopware6',
                $this->getContainer()->getParameter('kernel.project_dir'),
            ])
            ->onlyMethods(['createMigrationHelper'])
            ->getMock();

        $plugin->expects($this->once())
            ->method('createMigrationHelper')
            ->willReturn($migrationHelper);

        $plugin->setContainer($this->containerProxy);

        return $plugin;
    }

    private function disableEventDispatcherListeners(): void
    {
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        foreach ($dispatcher->getListeners() as $eventName => $listeners) {
            foreach ($listeners as $listener) {
                $dispatcher->removeListener($eventName, $listener);
            }
        }
    }
}

class MutableContainerProxy implements ContainerInterface
{
    /**
     * @var array<string, object>
     */
    private array $overrides = [];

    public function __construct(
        private readonly ContainerInterface $inner,
    ) {
    }

    public function set(string $id, ?object $service)
    {
        if ($service === null) {
            unset($this->overrides[$id]);

            return;
        }

        $this->overrides[$id] = $service;
    }

    public function get(string $id, int $invalidBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE): ?object
    {
        if (array_key_exists($id, $this->overrides)) {
            return $this->overrides[$id];
        }

        return $this->inner->get($id, $invalidBehavior);
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->overrides) || $this->inner->has($id);
    }

    public function initialized(string $id): bool
    {
        if (array_key_exists($id, $this->overrides)) {
            return true;
        }

        return method_exists($this->inner, 'initialized') ? $this->inner->initialized($id) : $this->inner->has($id);
    }

    public function getParameter(string $name)
    {
        return $this->inner->getParameter($name);
    }

    public function hasParameter(string $name): bool
    {
        return $this->inner->hasParameter($name);
    }

    public function setParameter(string $name, array|bool|string|int|float|\UnitEnum|null $value)
    {
        $this->inner->setParameter($name, $value);
    }
}
