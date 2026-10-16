<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration;

use Composer\Autoload\ClassLoader;
use Nosto\Request\Http\HttpRequest;
use Nosto\Scheduler\NostoScheduler;
use ReflectionClass;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
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
    public function boot(): void
    {
        parent::boot();

        $this->reportVersionsToNosto();
    }

    /**
     * Makes the SDK send the plugin and Shopware versions to Nosto.
     *
     * The SDK puts these in the User-Agent header of every API call it makes, and Nosto stores them
     * against the merchant's account.
     */
    private function reportVersionsToNosto(): void
    {
        self::classLoader();

        $pluginVersion = $this->getPluginVersion();

        // Both values end up in a header Nosto parses with a strict pattern, so send nothing at all
        // rather than sending a placeholder it would silently discard.
        if ($pluginVersion === null) {
            return;
        }

        HttpRequest::buildUserAgent(
            'Shopware',
            (string) $this->container->getParameter('kernel.shopware_version'),
            $pluginVersion,
        );
    }

    private function getPluginVersion(): ?string
    {
        $composerPath = dirname($this->getPath()) . '/composer.json';

        if (!is_file($composerPath)) {
            return null;
        }

        $composer = json_decode((string) file_get_contents($composerPath), true);

        return is_array($composer) && !empty($composer['version']) ? (string) $composer['version'] : null;
    }

    public function install(InstallContext $installContext): void
    {
        (new Utils\Lifecycle($this->container, true))->install($installContext);
        parent::install($installContext);
        $bundles = $this->getDependencyBundles();
        $this->migrateDependencyBundles($bundles);
    }

    public function update(UpdateContext $updateContext): void
    {
        (new Utils\Lifecycle($this->container, true))->update($updateContext);
        parent::update($updateContext);
        $bundles = $this->getDependencyBundles();
        $this->migrateDependencyBundles($bundles);
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
        $bundles = $this->getDependencyBundles();
        $this->migrateDependencyBundles($bundles);
        $this->copyDependencyBundleAssets($bundles);
    }

    /**
     * @param Bundle[] $bundles
     */
    private function migrateDependencyBundles(array $bundles): void
    {
        $migrationHelper = $this->createMigrationHelper();

        foreach ($bundles as $bundle) {
            $migrationHelper->getMigrationCollection($bundle)->migrateInPlace();
        }
    }

    protected function createMigrationHelper(): Utils\MigrationHelper
    {
        return new Utils\MigrationHelper(
            $this->container->get(MigrationCollectionLoader::class),
        );
    }

    /**
     * @param Bundle[] $bundles
     */
    protected function copyDependencyBundleAssets(array $bundles): void
    {
        /** @var AssetService $assetService */
        $assetService = $this->container->get('nosto.plugin.assetservice.public');

        foreach ($bundles as $bundle) {
            $assetService->copyAssetsFromBundle((new ReflectionClass($bundle))->getShortName());
        }
    }

    /**
     * @return Bundle[]
     */
    private function getDependencyBundles(): array
    {
        self::classLoader();

        return [
            new NostoScheduler(),
        ];
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

            $schedulerDependencies = array_filter(
                $bundle->getAdditionalBundles($bundleParameters),
                static fn (BundleInterface $bundle): bool => $bundle instanceof NostoScheduler,
            );

            if (count($schedulerDependencies) !== 0) {
                $hasOtherSchedulerDependency = true;
                break;
            }
        }

        (new Utils\Lifecycle($this->container, $hasOtherSchedulerDependency))->uninstall($uninstallContext);
    }

    /**
     * @return Bundle[]
     */
    public function getAdditionalBundles(AdditionalBundleParameters $parameters): array
    {
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
        $classLoader->register();
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

        $confDir = rtrim($this->getPath(), '/') . '/Resources/config';

        $configLoader->load($confDir . '/{packages}/*.yaml', 'glob');
    }

    public function executeComposerCommands(): bool
    {
        return true;
    }
}
