<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Utils;

use Doctrine\DBAL\Connection;
use Nosto\NostoIntegration\Service\JobRecoveryService;
use Nosto\NostoIntegration\Utils\Lifecycle;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class LifecycleTest extends TestCase
{
    public function testDeactivateFailsTheUnfinishedSyncJobs(): void
    {
        $jobRecoveryService = $this->createMock(JobRecoveryService::class);
        $jobRecoveryService->expects($this->once())
            ->method('failUnfinishedJobs')
            ->with('The Nosto plugin was deactivated.');
        $sortingRepository = $this->createMock(EntityRepository::class);
        $sortingRepository->method('search')->willReturn(new EntitySearchResult(
            ProductSortingEntity::class,
            0,
            new EntityCollection(),
            new AggregationResultCollection(),
            new Criteria(),
            Context::createDefaultContext(),
        ));
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturnMap([
            [
                Connection::class,
                ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE,
                $this->createMock(Connection::class),
            ],
            ['product_sorting.repository', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $sortingRepository],
            [
                'sales_channel.repository',
                ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE,
                $this->createMock(EntityRepository::class),
            ],
            [
                'system_config.repository',
                ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE,
                $this->createMock(EntityRepository::class),
            ],
            [JobRecoveryService::class, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $jobRecoveryService],
        ]);
        $deactivateContext = $this->createMock(DeactivateContext::class);
        $deactivateContext->method('getContext')->willReturn(Context::createDefaultContext());

        (new Lifecycle($container, true))->deactivate($deactivateContext);
    }
}
