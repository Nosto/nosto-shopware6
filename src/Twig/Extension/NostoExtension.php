<?php

namespace Nosto\NostoIntegration\Twig\Extension;

use Nosto\Model\Product\Product as NostoProduct;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\ProductProviderInterface;
use Nosto\NostoIntegration\Utils\Logger\ContextHelper;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class NostoExtension extends AbstractExtension
{
    private ProductProviderInterface $productProvider;
    private LoggerInterface $logger;

    public function __construct(
        ProductProviderInterface $productProvider,
        LoggerInterface $logger
    ) {
        $this->productProvider = $productProvider;
        $this->logger = $logger;
    }

    /**
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('nosto_product', [$this, 'getNostoProduct']),
            new TwigFunction('nosto_page_type', [$this, 'getPageType'])
        ];
    }


    public function getNostoProduct(?SalesChannelProductEntity $product, SalesChannelContext $context): ?NostoProduct
    {
        try {
            return $product === null ? null : $this->productProvider->get($product, $context);
        } catch (\Throwable $throwable) {
            $this->logger->error(
                $throwable->getMessage(),
                ContextHelper::createContextFromException($throwable)
            );
            return null;
        }
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
