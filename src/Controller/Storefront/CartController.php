<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Controller\Storefront;

use Nosto\NostoIntegration\Storefront\Checkout\Cart\RestorerService\RestorerServiceInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(
    defaults: [
        '_routeScope' => ['storefront'],
    ]
)]
class CartController extends StorefrontController
{
    private RestorerServiceInterface $restorerService;

    public function __construct(RestorerServiceInterface $restorerService)
    {
        $this->restorerService = $restorerService;
    }

    #[Route(
        path: "/nosto-restore-cart/{mappingId}",
        name: "frontend.cart.nosto-restore-cart",
        options: [
            "seo" => "false",
        ],
        methods: ["GET"]
    )]
    public function index(string $mappingId, SalesChannelContext $context): Response
    {
        $this->restorerService->restore($mappingId, $context);
        return $this->redirectToRoute('frontend.checkout.confirm.page');
    }
}
