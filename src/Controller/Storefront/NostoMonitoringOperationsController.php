<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Controller\Storefront;

use Nosto\NostoIntegration\Model\Nosto\Entity\Helper\NostoMonitoringHelper;
use Nosto\NostoIntegration\Service\NostoMonitoringAuthService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: [
    '_routeScope' => ['storefront'],
])]
class NostoMonitoringOperationsController extends AbstractNostoMonitoringController
{
    public function __construct(
        NostoMonitoringAuthService $authService,
        private readonly NostoMonitoringHelper $nostoMonitoringHelper,
        private readonly EntityRepository $languageRepository,
        private readonly EntityRepository $salesChannelRepository,
        private readonly ParameterBagInterface $parameterBag,
    ) {
        parent::__construct($authService);
    }

    #[Route(
        path: "/nosto-monitoring/manage-operations",
        name: "nosto-monitoring.manage-operations",
        options: [
            "seo" => "false",
        ],
        methods: ["GET"],
    )]
    public function nostoMangeOperations(Request $request, Context $context): Response
    {
        if ($redirect = $this->requireAuthentication($request)) {
            return $redirect;
        }

        $currentLanguageId = $request->get('languageId');
        $currentSalesChannelId = $request->get('salesChannelId');

        $languageCriteria = new Criteria();
        $languageCriteria->addAssociation('locale');
        $languages = $this->languageRepository->search($languageCriteria, $context)->getEntities();

        $salesChannels = $this->salesChannelRepository->search(new Criteria(), $context)->getEntities();

        $composerFile = $this->parameterBag->get(
            'kernel.project_dir',
        ) . '/custom/plugins/NostoIntegration/composer.json';
        if (file_exists($composerFile)) {
            $composerData = json_decode(file_get_contents($composerFile), true);
            $nostoVersion = $composerData['version'] ?? 'Unknown';
        } else {
            $nostoVersion = 'Unknown';
        }

        return $this->renderStorefront(
            '@NostoMonitoringController/storefront/page/nosto-monitoring/manage-operations.html.twig',
            [
                'nostoConfig' => $this->nostoMonitoringHelper->getSerializedNostoConfiguration(
                    $request->get('salesChannelId'),
                    $request->get('languageId'),
                ),
                'languages' => $languages,
                'salesChannels' => $salesChannels,
                'currentLanguageId' => $currentLanguageId,
                'currentSalesChannelId' => $currentSalesChannelId,
                'nostoVersion' => $nostoVersion,
            ],
        );
    }

    #[Route(
        path: "/nosto-monitoring/clear-jobs",
        name: "nosto-monitoring.clear-jobs",
        options: [
            "seo" => "false",
        ],
        methods: ["POST"],
    )]
    public function clearNostoJobsFromDB(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireAuthentication($request)) {
            return $redirect;
        }

        $clearedJobs = $this->nostoMonitoringHelper->clearNostoJobs();
        $request->getSession()->getFlashBag()->add($clearedJobs['status'], $clearedJobs['message']);

        return $this->redirectToRoute('nosto-monitoring.manage-operations');
    }
}
