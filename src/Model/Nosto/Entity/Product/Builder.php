<?php declare(strict_types=1);

namespace Od\NostoIntegration\Model\Nosto\Entity\Product;

use Nosto\Model\Product\Product as NostoProduct;
use Nosto\Types\Product\ProductInterface;
use Od\NostoIntegration\Model\ConfigProvider;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class Builder
{
    private UrlGeneratorInterface $urlGenerator;
    private ConfigProvider $configProvider;

    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        ConfigProvider $configProvider
    ) {
        $this->urlGenerator = $urlGenerator;
        $this->configProvider = $configProvider;
    }

    public function build(ProductEntity $product, SalesChannelContext $context): NostoProduct
    {
        $nostoProduct = new NostoProduct();
        $nostoProduct->setUrl($this->getProductUrl($product));
        $nostoProduct->setProductId($product->getId());
        $nostoProduct->setName($product->getTranslation('name'));
//        $nostoProduct->setImageUrl();
        $stockStatus = $product->getAvailableStock() > 0 ? ProductInterface::IN_STOCK : ProductInterface::OUT_OF_STOCK;
        $nostoProduct->setAvailability($stockStatus);
//        $nostoProduct->setCategories();

//        if ($this->configProvider->isInventoryTaggingEnabled()) {
            $nostoProduct->setInventoryLevel($product->getAvailableStock());
//        }

//        if ($this->configProvider->isTagDatePublishedEnabled()) {
            $nostoProduct->setDatePublished($product->getCreatedAt()->format('Y-m-d'));
//        }

        $price = $product->getCurrencyPrice($context->getCurrencyId());
        $nostoProduct->setPrice($price->getGross());

        if ($price->getListPrice() !== null) {
            $nostoProduct->setListPrice($price->getListPrice()->getGross());
        }
        $nostoProduct->setPriceCurrencyCode($context->getCurrency()->getIsoCode());

        return $nostoProduct;
    }

    protected function getProductUrl(ProductEntity $product)
    {
        return $this->urlGenerator->generate(
            'frontend.detail.page',
            ['productId' => $product->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }
}
