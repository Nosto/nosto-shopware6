<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Twig\Extension;

use Nosto\Model\Product\Product as NostoProduct;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\ProductProviderInterface;
use Nosto\NostoIntegration\Utils\Logger\ContextHelper;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Throwable;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class NostoExtension extends AbstractExtension
{
    public function __construct(
        private readonly ProductProviderInterface $productProvider,
        private readonly LoggerInterface $logger,
        private readonly SalesChannelRepository $salesChannelProductRepository,
    ) {
    }

    /**
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('nosto_product', [$this, 'getNostoProduct']),
            new TwigFunction('nosto_page_type', [$this, 'getPageType']),
            new TwigFunction('nosto_product_by_id', [$this, 'getNostoProductByID']),
        ];
    }

    public function getNostoProduct(?SalesChannelProductEntity $product, SalesChannelContext $context): ?NostoProduct
    {
        try {
            $variationStatus = $this->resolveVariations($product);
            $result = $product === null ? null : $this->productProvider->get($product, $context);
            $result->variationStatus = $variationStatus;

            return $result;
        } catch (Throwable $throwable) {
            $this->logger->error(
                $throwable->getMessage(),
                ContextHelper::createContextFromException($throwable),
            );

            return null;
        }
    }

    private function resolveVariations($product): string
    {
        if ($variantsConfig = $product->getVariantListingConfig()) {
            if (!$variantsConfig->getDisplayParent() && $variantsConfig->getMainVariantId()) {
                return 'singleProduct';
            } elseif ($variantsConfig->getDisplayParent()) {
                return 'singleProduct';
            } else {
                return 'variantProducts';
            }
        }

        return 'nothing';
    }

    public function getNostoProductByID($id, SalesChannelContext $context): ?NostoProduct
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $id));

        return $this->salesChannelProductRepository
            ->search($criteria, $context)
            ->first();
    }

    public function getPageType($activeRoute, $pageCmsType): string
    {
        $pageType = 'notfound';

        if (empty($activeRoute)) {
            return $pageType;
        }

        switch ($activeRoute) {
            case 'frontend.home.page':
                $pageType = 'front';
                break;
            case 'frontend.navigation.page':
                if ($pageCmsType == 'product_list') {
                    $pageType = 'category';
                } else {
                    $pageType = 'other';
                }
                break;
            case 'frontend.detail.page':
                $pageType = 'product';
                break;
            case 'frontend.checkout.cart.page':
                $pageType = 'cart';
                break;
            case 'frontend.checkout.register.page':
            case 'frontend.checkout.confirm.page':
                $pageType = 'checkout';
                break;
            case 'frontend.checkout.finish.page':
                $pageType = 'order';
                break;
            case 'frontend.search.page':
                $pageType = 'search';
                break;
            default:
                $pageType = 'other';
        }

        return $pageType;
    }
}
