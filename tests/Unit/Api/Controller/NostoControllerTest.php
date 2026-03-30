<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Tests\Unit\Api\Controller;

use Nosto\NostoIntegration\Api\Controller\NostoController;
use Nosto\NostoIntegration\Api\Route\NostoSyncRoute;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\CachedProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Response\JsonApiResponse;
use Shopware\Core\Framework\Context;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class NostoControllerTest extends TestCase
{
    public function testFullCatalogSyncActionDelegatesToRoute(): void
    {
        $request = new Request();
        $context = Context::createDefaultContext();

        $route = $this->createMock(NostoSyncRoute::class);
        $route->expects($this->once())
            ->method('fullCatalogSync')
            ->with($request, $context)
            ->willReturn(new JsonApiResponse());

        $controller = new NostoController(
            $route,
            $this->createMock(TagAwareAdapterInterface::class),
        );

        self::assertInstanceOf(JsonApiResponse::class, $controller->fullCatalogSyncAction($request, $context));
    }

    public function testClearCacheUsesTheProductCachePrefix(): void
    {
        $cache = $this->createMock(TagAwareAdapterInterface::class);
        $cache->expects($this->once())
            ->method('clear')
            ->with(CachedProvider::CACHE_PREFIX);

        $controller = new NostoController(
            $this->createMock(NostoSyncRoute::class),
            $cache,
        );

        self::assertInstanceOf(JsonResponse::class, $controller->clearCache());
    }
}
