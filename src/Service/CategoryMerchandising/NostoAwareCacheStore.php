<?php declare(strict_types=1);

namespace Od\NostoIntegration\Service\CategoryMerchandising;

use Shopware\Core\Framework\Adapter\Cache\{AbstractCacheTracer, CacheCompressor};
use Shopware\Storefront\Framework\Cache\{AbstractHttpCacheKeyGenerator, CacheStateValidator, CacheStore};
use Shopware\Storefront\Framework\Cache\Event\HttpCacheHitEvent;
use Shopware\Storefront\Framework\Routing\{MaintenanceModeResolver, RequestTransformer};
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class NostoAwareCacheStore extends CacheStore
{
    private TagAwareAdapterInterface $cache;
    private CacheStateValidator $stateValidator;
    private EventDispatcherInterface $eventDispatcher;
    private AbstractHttpCacheKeyGenerator $cacheKeyGenerator;
    private MaintenanceModeResolver $maintenanceResolver;

    public function __construct(
        TagAwareAdapterInterface $cache,
        CacheStateValidator $stateValidator,
        EventDispatcherInterface $eventDispatcher,
        AbstractCacheTracer $tracer,
        AbstractHttpCacheKeyGenerator $cacheKeyGenerator,
        MaintenanceModeResolver $maintenanceModeResolver
    ) {
        parent::__construct($cache, $stateValidator, $eventDispatcher, $tracer, $cacheKeyGenerator,
            $maintenanceModeResolver);
        $this->cache = $cache;
        $this->stateValidator = $stateValidator;
        $this->eventDispatcher = $eventDispatcher;
        $this->cacheKeyGenerator = $cacheKeyGenerator;
        $this->maintenanceResolver = $maintenanceModeResolver;
    }

    public function lookup(Request $request): ?Response
    {
        // maintenance mode active and current ip is whitelisted > disable caching
        if ($this->maintenanceResolver->isMaintenanceRequest($request)) {
            return null;
        }

        $key = $this->cacheKeyGenerator->generate($request);

        $item = $this->cache->getItem($key);

        if (!empty($request->attributes->count())
            && strpos($request->attributes->get(RequestTransformer::SALES_CHANNEL_RESOLVED_URI), 'navigation')) {
            $item->set(null);
        }

        if (!$item->isHit() || !$item->get()) {
            return null;
        }

        /** @var Response $response */
        $response = CacheCompressor::uncompress($item);

        if (!$this->stateValidator->isValid($request, $response)) {
            return null;
        }

        $this->eventDispatcher->dispatch(
            new HttpCacheHitEvent($item, $request, $response)
        );

        return $response;
    }

}