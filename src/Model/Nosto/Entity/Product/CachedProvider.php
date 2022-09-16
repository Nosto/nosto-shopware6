<?php declare(strict_types=1);

namespace Od\NostoIntegration\Model\Nosto\Entity\Product;

use Nosto\Model\Product\Product as NostoProduct;
use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Content\Product\SalesChannel\Detail\CachedProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;

class CachedProvider implements ProductProviderInterface
{
    public const CACHE_PREFIX = 'od_nosto_product_';
    private TagAwareAdapterInterface $cache;
    private Provider $innerProvider;

    public function __construct(
        TagAwareAdapterInterface $cache,
        Provider $innerProvider
    ) {
        $this->cache = $cache;
        $this->innerProvider = $innerProvider;
    }

    public function get(SalesChannelProductEntity $product, SalesChannelContext $context): NostoProduct
    {
        $cacheKey = self::CACHE_PREFIX . $product->getId();
        $cachedItem = $this->cache->getItem($cacheKey);

        if ($cachedItem->isHit()) {
            return $cachedItem->get();
        }

        $nostoProduct = $this->innerProvider->get($product, $context);
        $cachedItem->expiresAfter(3600);
        $cachedItem->tag(CachedProductDetailRoute::buildName($product->getParentId() ?? $product->getId()));
        $this->cache->save($cachedItem->set($nostoProduct));

        return $nostoProduct;
    }
}
