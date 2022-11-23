<?php declare(strict_types=1);

namespace Od\NostoIntegration;

use Composer\Autoload\ClassLoader;
use Od\NostoIntegration\Utils\Loader\FlexibleXmlFileLoader;
use Od\Scheduler\OdScheduler;
use Shopware\Core\Framework\Parameter\AdditionalBundleParameters;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Util\AssetService;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

class overdose_nosto extends Plugin
{
    public function activate(ActivateContext $activateContext): void
    {
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
        return [
            new OdScheduler()
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

            $schedulerDependencies = \array_filter(
                $bundle->getAdditionalBundles($bundleParameters),
                function (BundleInterface $bundle) {
                    return $bundle instanceof OdScheduler;
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
        $this->registerContainerFile($container);
        parent::build($container);
    }

    private function registerContainerFile(ContainerBuilder $container): void
    {
        $fileLocator = new FileLocator($this->getPath());
        $loaderResolver = new LoaderResolver([
            new FlexibleXmlFileLoader($container, $fileLocator),
        ]);
        $delegatingLoader = new DelegatingLoader($loaderResolver);

        $path = $this->getPath().'/Resources/config/flexible_services.xml';
        if (file_exists($path)) {
            $delegatingLoader->load($path);
        }
    }
}