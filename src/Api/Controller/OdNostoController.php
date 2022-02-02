<?php

declare(strict_types=1);

namespace Od\NostoIntegration\Api\Controller;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class OdNostoController extends AbstractController
{

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/_action/od-nosto/schedule-full-product-sync",
     *     name="api.action.od-nosto.schedule.full.product.sync",
     *     methods={"POST"}
     * )
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request, Context $context): JsonResponse
    {


        return new JsonResponse();
    }
}
