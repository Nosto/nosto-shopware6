<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration;

use Composer\Autoload\ClassLoader;
use Nosto\Scheduler\NostoScheduler;
use Shopware\Core\Framework\Parameter\AdditionalBundleParameters;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Plugin\Util\AssetService;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\DirectoryLoader;
use Symfony\Component\DependencyInjection\Loader\GlobFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

class NostoIntegration extends Plugin
{
    public function install(InstallContext $installContext): void
    {
        (new Utils\Lifecycle($this->container, true))->install($installContext);
        parent::install($installContext);
    }

    public function update(UpdateContext $updateContext): void
    {
        (new Utils\Lifecycle($this->container, true))->update($updateContext);
        parent::update($updateContext);
    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
        (new Utils\Lifecycle($this->container, true))->deactivate($deactivateContext);
        parent::deactivate($deactivateContext);
    }

    public function activate(ActivateContext $activateContext): void
    {
        (new Utils\Lifecycle($this->container, true))->activate($activateContext);
        parent::activate($activateContext);
        /** @var AssetService $assetService */
        $assetService = $this->container->get('nosto.plugin.assetservice.public');
        /** @var Utils\MigrationHelper $migrationHelper */
        $migrationHelper = $this->container->get(Utils\MigrationHelper::class);

        foreach ($this->getDependencyBundles() as $bundle) {
            $migrationHelper->getMigrationCollection($bundle)->migrateInPlace();
            $assetService->copyAssetsFromBundle((new \ReflectionClass($bundle))->getShortName());
        }
    }

    private function getDependencyBundles(): array
    {
        return [new NostoScheduler()];
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        if ($uninstallContext->keepUserData()) {
            return;
        }

        $hasOtherSchedulerDependency = false;
        $bundleParameters = new AdditionalBundleParameters(new ClassLoader(), new Plugin\KernelPluginCollection(), []);
        $kernel = $this->container->get('kernel');

        foreach ($kernel->getPluginLoader()->getPluginInstances()->getActives() as $bundle) {
            if (!$bundle instanceof Plugin || $bundle instanceof self) {
                continue;
            }

            $schedulerDependencies = \array_filter(
                $bundle->getAdditionalBundles($bundleParameters),
                function (BundleInterface $bundle) {
                    return $bundle instanceof NostoScheduler;
                }
            );

            if (\count($schedulerDependencies) !== 0) {
                $hasOtherSchedulerDependency = true;
                break;
            }
        }

        (new Utils\Lifecycle($this->container, $hasOtherSchedulerDependency))->uninstall($uninstallContext);
    }

    public function getAdditionalBundles(AdditionalBundleParameters $parameters): array
    {
        self::classLoader();

        return $this->getDependencyBundles();
    }

    public static function classLoader(): void
    {
        $file = __DIR__ . '/../vendor/autoload.php';
        if (!is_file($file)) {
            return;
        }

        /** @noinspection UsingInclusionOnceReturnValueInspection */
        $classLoader = require_once $file;

        if (!$classLoader instanceof ClassLoader) {
            return;
        }

        $classLoader->unregister();
        $classLoader->register(false);
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $locator = new FileLocator('Resources/config');

        $resolver = new LoaderResolver([
            new YamlFileLoader($container, $locator),
            new GlobFileLoader($container, $locator),
            new DirectoryLoader($container, $locator),
        ]);

        $configLoader = new DelegatingLoader($resolver);

        $confDir = \rtrim($this->getPath(), '/') . '/Resources/config';

        $configLoader->load($confDir . '/{packages}/*.yaml', 'glob');
    }
}
