<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Controller\Storefront;

use Nosto\NostoIntegration\Service\NostoMonitoringAuthService;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractNostoMonitoringController extends StorefrontController
{
    public function __construct(
        protected readonly NostoMonitoringAuthService $authService,
    ) {
    }

    protected function requireAuthentication(Request $request): ?RedirectResponse
    {
        if (!$this->authService->isAuthenticated($request->getSession())) {
            return $this->redirectToRoute('nosto-monitoring.access-page');
        }

        return null;
    }
}
