<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Controller\Storefront;

use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: [
    '_routeScope' => ['storefront'],
])]
class NostoMonitoringAuthController extends AbstractNostoMonitoringController
{
    public const ACCESS_KEY_PROPERTY = 'nostoAccessKey';

    #[Route(
        path: "/nosto-monitoring",
        name: "nosto-monitoring.access-page",
        options: [
            "seo" => "false",
        ],
        methods: ["GET"],
    )]
    public function index(Request $request): Response
    {

        if (!$this->authService->isAuthenticated($request->getSession())) {
            return $this->renderStorefront(
                '@NostoMonitoringController/storefront/page/nosto-monitoring/monitoring-access.html.twig',
                [],
            );
        }

        return $this->redirectToRoute('nosto-monitoring.manage-operations');
    }

    #[Route(
        path: "/nosto-monitoring/validate-access-key",
        name: "nosto-monitoring.access-key.validation",
        options: [
            "seo" => "false",
        ],
        methods: ["POST"],
    )]
    public function validateAccessKey(Request $request, Context $context): RedirectResponse
    {
        $accessKey = $request->request->get(self::ACCESS_KEY_PROPERTY);
        if (!is_string($accessKey) || empty($accessKey)) {
            return $this->redirectToRoute('nosto-monitoring.access-page');
        }

        $isValid = $this->authService->validateAccessKey($accessKey, $context);

        if (!$isValid) {
            return $this->redirectToRoute('nosto-monitoring.access-page');
        }

        $this->authService->authenticate($request->getSession());
        return $this->redirectToRoute('nosto-monitoring.manage-operations');
    }

    #[Route(
        path: "/nosto-monitoring/logout",
        name: "nosto-monitoring.logout",
        options: [
            "seo" => "false",
        ],
        methods: ["POST"],
    )]
    public function logout(Request $request): RedirectResponse
    {
        $this->authService->logout($request->getSession());

        return $this->redirectToRoute('nosto-monitoring.access-page');
    }
}
