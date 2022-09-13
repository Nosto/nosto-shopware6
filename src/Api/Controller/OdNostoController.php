<?php declare(strict_types=1);

namespace Od\NostoIntegration\Api\Controller;

use Od\NostoIntegration\Api\Route\OdNostoSyncRoute;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class OdNostoController extends AbstractController
{
    private OdNostoSyncRoute $nostoSyncRoute;

    public function __construct(OdNostoSyncRoute $nostoSyncRoute)
    {
        $this->nostoSyncRoute = $nostoSyncRoute;
    }

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
    public function fullCatalogSyncAction(Request $request, Context $context): JsonResponse
    {
        return $this->nostoSyncRoute->fullCatalogSync($request, $context);
    }
}
