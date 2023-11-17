<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Twig\Extension;

use Nosto\NostoIntegration\Model\Nosto\Entity\Customer\BuilderInterface;
use Nosto\NostoIntegration\Storefront\Checkout\Cart\RestoreUrlService\RestoreUrlService;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CustomerExtension extends AbstractExtension
{
    private BuilderInterface $builder;

    private RestoreUrlService $restoreUrlService;

    public function __construct(
        BuilderInterface $builder,
        RestoreUrlService $restoreUrlService,
    ) {
        $this->builder = $builder;
        $this->restoreUrlService = $restoreUrlService;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('nosto_customer', [$this, 'getNostoCustomer']),
            new TwigFunction('nosto_restore_cart_link', [$this, 'getRestoreCartLink']),
        ];
    }

    public function getNostoCustomer(CustomerEntity $customer, Context $context)
    {
        return $this->builder->build($customer, $context);
    }

    public function getRestoreCartLink(\Shopware\Core\System\SalesChannel\SalesChannelContext $context): string
    {
        $url = $this->restoreUrlService->getCurrentRestoreUrl($context);

        return $url;
    }
}
