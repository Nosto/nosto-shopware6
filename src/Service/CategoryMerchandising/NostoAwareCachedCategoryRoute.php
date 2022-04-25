<?php declare(strict_types=1);

namespace Od\NostoIntegration\Service\CategoryMerchandising;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Category\Event\{CategoryRouteCacheKeyEvent, CategoryRouteCacheTagsEvent};
use Shopware\Core\Content\Category\SalesChannel\{AbstractCategoryRoute, CategoryRouteResponse};
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\SalesChannel\Struct\{ProductBoxStruct, ProductSliderStruct};
use Shopware\Core\Framework\Adapter\Cache\{AbstractCacheTracer, CacheCompressor};
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class NostoAwareCachedCategoryRoute extends AbstractCategoryRoute
{
    private AbstractCategoryRoute $decoratedService;
    private TagAwareAdapterInterface $cache;
    private EntityCacheKeyGenerator $generator;
    private AbstractCacheTracer $tracer;
    private EventDispatcherInterface $dispatcher;
    private array $states;
    private LoggerInterface $logger;

    public function __construct(
        AbstractCategoryRoute $cachedCategoryRoute,
        TagAwareAdapterInterface $cache,
        EntityCacheKeyGenerator $generator,
        AbstractCacheTracer $tracer,
        EventDispatcherInterface $dispatcher,
        array $states,
        LoggerInterface $logger
    ) {
        $this->decoratedService = $cachedCategoryRoute;
        $this->cache = $cache;
        $this->generator = $generator;
        $this->tracer = $tracer;
        $this->dispatcher = $dispatcher;
        $this->states = $states;
        $this->logger = $logger;
    }

    /**
     * @Since("6.2.0.0")
     * @OA\Post(
     *     path="/category/{categoryId}",
     *     summary="Fetch a single category",
     *     description="This endpoint returns information about the category, as well as a fully resolved (hydrated with mapping values) CMS page, if one is assigned to the category. You can pass slots which should be resolved exclusively.",
     *     operationId="readCategory",
     *     tags={"Store API", "Category"},
     *     @OA\Parameter(
     *         name="categoryId",
     *         description="Identifier of the category to be fetched",
     *         @OA\Schema(type="string", pattern="^[0-9a-f]{32}$"),
     *         in="path",
     *         required=true
     *     ),
     *     @OA\Parameter(
     *         name="slots",
     *         description="Resolves only the given slot identifiers. The identifiers have to be seperated by a '|' character",
     *         @OA\Schema(type="string"),
     *         in="query",
     *     ),
     *     @OA\Parameter(name="Api-Basic-Parameters"),
     *     @OA\Response(
     *          response="200",
     *          description="The loaded category with cms page",
     *          @OA\JsonContent(ref="#/components/schemas/Category")
     *     )
     * )
     *
     * @Route("/store-api/category/{navigationId}", name="store-api.category.detail", methods={"GET","POST"})
     */
    public function load(
        string $navigationId,
        Request $request,
        SalesChannelContext $context
    ): CategoryRouteResponse {
        if ($context->hasState(...$this->states)) {
            $this->logger->info('cache-miss: ' . self::buildName($navigationId));

            return $this->getDecorated()->load($navigationId, $request, $context);
        }

        $item = $this->cache->getItem(
            $this->generateKey($navigationId, $request, $context)
        );

        if (strpos($request->attributes->get(RequestTransformer::SALES_CHANNEL_RESOLVED_URI), 'navigation')) {
            $item->set(null);
        }

        try {
            if ($item->isHit() && $item->get()) {
                $this->logger->info('cache-hit: ' . self::buildName($navigationId));

                return CacheCompressor::uncompress($item);
            }
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
        }

        $this->logger->info('cache-miss: ' . self::buildName($navigationId));

        $name = self::buildName($navigationId);
        $response = $this->tracer->trace($name, function () use ($navigationId, $request, $context) {
            return $this->decoratedService->getDecorated()->load($navigationId, $request, $context);
        });

        $item = CacheCompressor::compress($item, $response);

        $item->tag($this->generateTags($navigationId, $response, $request, $context));

        $this->cache->save($item);

        return $response;
    }

    public static function buildName(string $id): string
    {
        return 'category-route-' . $id;
    }

    public function getDecorated(): AbstractCategoryRoute
    {
        return $this->decoratedService;
    }

    private function generateKey(
        string $navigationId,
        Request $request,
        SalesChannelContext $context
    ): string {
        $parts = array_merge(
            $request->query->all(),
            $request->request->all(),
            [
                self::buildName($navigationId),
                $this->generator->getSalesChannelContextHash($context),
            ]
        );

        $event = new CategoryRouteCacheKeyEvent($navigationId, $parts, $request, $context, null);
        $this->dispatcher->dispatch($event);

        return md5(JsonFieldSerializer::encodeJson($event->getParts()));
    }

    private function generateTags(
        string $navigationId,
        CategoryRouteResponse $response,
        Request $request,
        SalesChannelContext $context
    ): array {
        $tags = array_merge(
            $this->tracer->get(self::buildName($navigationId)),
            $this->extractProductIds($response),
            [self::buildName($navigationId)]
        );

        $event = new CategoryRouteCacheTagsEvent($navigationId, $tags, $request, $response, $context, null);
        $this->dispatcher->dispatch($event);

        return array_unique(array_filter($event->getTags()));
    }

    private function extractProductIds(CategoryRouteResponse $response): array
    {
        $page = $response->getCategory()->getCmsPage();

        if ($page === null) {
            return [];
        }

        $ids = [];

        $slots = $page->getElementsOfType('product-slider');
        /** @var CmsSlotEntity $slot */
        foreach ($slots as $slot) {
            $slider = $slot->getData();

            if (!$slider instanceof ProductSliderStruct) {
                continue;
            }

            if ($slider->getProducts() === null) {
                continue;
            }
            foreach ($slider->getProducts() as $product) {
                $ids[] = $product->getId();
                $ids[] = $product->getParentId();
            }
        }

        $slots = $page->getElementsOfType('product-box');
        /** @var CmsSlotEntity $slot */
        foreach ($slots as $slot) {
            $box = $slot->getData();

            if (!$box instanceof ProductBoxStruct) {
                continue;
            }
            if ($box->getProduct() === null) {
                continue;
            }

            $ids[] = $box->getProduct()->getId();
            $ids[] = $box->getProduct()->getParentId();
        }

        $ids = array_values(array_unique(array_filter($ids)));

        return array_merge(
            array_map([EntityCacheKeyGenerator::class, 'buildProductTag'], $ids),
            [EntityCacheKeyGenerator::buildCmsTag($page->getId())]
        );
    }
}