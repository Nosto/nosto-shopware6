<?php

declare(strict_types=1);

namespace Od\NostoIntegration\Api\Controller;

use Nosto\Model\Signup\Account as NostoSignupAccount;
use Nosto\NostoException;
use Nosto\Request\Api\Token as NostoToken;
use Od\NostoIntegration\Api\Route\OdNostoSyncRoute;
use Od\NostoIntegration\Model\MockOperation\MockGraphQLOperation;
use Od\NostoIntegration\Model\MockOperation\MockMarketingPermission;
use Od\NostoIntegration\Model\MockOperation\MockUpsertProduct;
use OpenApi\Annotations as OA;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


class OdNostoController extends AbstractController
{
    protected const NAME_TOKEN = 'name';
    protected const PRODUCT_TOKEN = 'productToken';
    protected const EMAIL_TOKEN = 'emailToken';
    protected const APP_TOKEN = 'appToken';

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

    /**
     * @RouteScope(scopes={"api"})
     * @OA\Post(
     *     path="/_action/od-nosto-api-key-validate",
     *     summary="Validate api keys for Nosto",
     *     description="Validates if the given api keys are valid for Nosto",
     *     operationId="od-api-validate",
     *     tags={"Admin API", "Od Validation"},
     *     @OA\RequestBody(
     *         required=true
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns a json response file with validation info."
     *     )
     * )
     * @Route("/api/_action/od-nosto-api-key-validate", name="api.action.od_nosto_api_key_validate", methods={"POST"}, defaults={"auth_required"=false})
     */
    public function validate(RequestDataBag $post): JsonResponse
    {
        try {
            $account = new NostoSignupAccount($post->get(self::NAME_TOKEN));
        } catch (NostoException $e) {
            return new JsonResponse([self::NAME_TOKEN => ['success' => false, 'message' => $e->getMessage()]]);
        }

        $account->addApiToken(new NostoToken(NostoToken::API_PRODUCTS, $post->get(self::PRODUCT_TOKEN)));
        $account->addApiToken(new NostoToken(NostoToken::API_EMAIL, $post->get(self::EMAIL_TOKEN)));
        $account->addApiToken(new NostoToken(NostoToken::API_GRAPHQL, $post->get(self::APP_TOKEN)));

        $result = [];
        $result[self::PRODUCT_TOKEN] = (new MockUpsertProduct($account))->upsert();
        $result[self::EMAIL_TOKEN] = (new MockMarketingPermission($account))->mockUpdate();
        $result[self::APP_TOKEN] = (new MockGraphQLOperation($account))->execute();

        return new JsonResponse($result, Response::HTTP_OK);
    }
}
