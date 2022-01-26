<?php declare(strict_types=1);

namespace Od\NostoIntegration\Model\Nosto\Entity\Product;

use Nosto\Model\Product\Product as NostoProduct;
use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CachedProvider implements ProductProviderInterface
{
    private CacheItemPoolInterface $cache;
    private Provider $innerProvider;

    public function __construct(
        CacheItemPoolInterface $cache,
        Provider $innerProvider
    ) {
        $this->cache = $cache;
        $this->innerProvider = $innerProvider;
    }

    public function get(ProductEntity $product, SalesChannelContext $context): NostoProduct
    {
        $cacheKey = 'od_nosto_product_' . $product->getId();
        $cachedItem = $this->cache->getItem($cacheKey);

        if ($cachedItem->isHit()) {
            return $cachedItem->get();
        }

        $nostoProduct = $this->innerProvider->get($product, $context);
        $this->cache->save($cachedItem->set($nostoProduct));

        return $nostoProduct;
    }
}
