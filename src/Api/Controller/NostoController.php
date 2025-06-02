<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Api\Controller;

use Nosto\Model\Signup\Account as NostoSignupAccount;
use Nosto\NostoException;
use Nosto\NostoIntegration\Api\Route\NostoSyncRoute;
use Nosto\NostoIntegration\Model\MockOperation\MockGraphQLOperation;
use Nosto\NostoIntegration\Model\MockOperation\MockMarketingPermission;
use Nosto\NostoIntegration\Model\MockOperation\MockSearchOperation;
use Nosto\NostoIntegration\Model\MockOperation\MockUpsertProduct;
use Nosto\NostoIntegration\Model\Nosto\Entity\Product\CachedProvider;
use Nosto\Request\Api\Token as NostoToken;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(
    defaults: [
        '_routeScope' => ['api'],
    ],
)]
class NostoController extends AbstractController
{
    protected const ACCOUNT_ID = 'accountId';

    protected const NAME_TOKEN = 'name';

    protected const PRODUCT_TOKEN = 'productToken';

    protected const EMAIL_TOKEN = 'emailToken';

    protected const APP_TOKEN = 'appToken';

    protected const SEARCH_TOKEN = 'searchToken';

    public function __construct(
        private readonly NostoSyncRoute $nostoSyncRoute,
        private readonly TagAwareAdapterInterface $cache,
    ) {
    }

    #[Route(
        path: "/api/_action/nosto-integration/schedule-full-product-sync",
        name: "api.action.nosto_integration.schedule.full.product.sync",
        methods: ["POST"],
    )]
    public function fullCatalogSyncAction(Request $request, Context $context): JsonResponse
    {
        return $this->nostoSyncRoute->fullCatalogSync($request, $context);
    }

    #[Route(
        path: "/api/_action/nosto-integration/clear-cache",
        name: "api.action.nosto_integration.clear.cache",
        methods: ["POST"],
    )]
    public function clearCache(): JsonResponse
    {
        $this->cache->clear(CachedProvider::CACHE_PREFIX);
        return new JsonResponse();
    }

    #[Route(
        path: "/api/_action/nosto-integration-api-key-validate",
        name: "api.action.nosto_integration_api_key_validate",
        options: [
            "auth_required" => "false",
        ],
        methods: ["POST"],
    )]
    public function validate(RequestDataBag $post): JsonResponse
    {
        try {
            $account = new NostoSignupAccount($post->get(self::NAME_TOKEN));
        } catch (NostoException $e) {
            return new JsonResponse([
                self::NAME_TOKEN => [
                    'success' => false,
                    'message' => $e->getMessage(),
                ],
            ]);
        }

        $account->addApiToken(new NostoToken(NostoToken::API_PRODUCTS, $post->get(self::PRODUCT_TOKEN)));
        $account->addApiToken(new NostoToken(NostoToken::API_EMAIL, $post->get(self::EMAIL_TOKEN)));
        $account->addApiToken(new NostoToken(NostoToken::API_GRAPHQL, $post->get(self::APP_TOKEN)));
        $account->addApiToken(new NostoToken(NostoToken::API_SEARCH, $post->get(self::SEARCH_TOKEN)));

        $result = [];
        $result[self::PRODUCT_TOKEN] = (new MockUpsertProduct($account))->upsert();
        $result[self::EMAIL_TOKEN] = (new MockMarketingPermission($account))->mockUpdate();
        $result[self::APP_TOKEN] = (new MockGraphQLOperation($account))->execute();
        $result[self::SEARCH_TOKEN] = (new MockSearchOperation($post->get(self::ACCOUNT_ID), $account))->execute();

        return new JsonResponse($result, Response::HTTP_OK);
    }
}
