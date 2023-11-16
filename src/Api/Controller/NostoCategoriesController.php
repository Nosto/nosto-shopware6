<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Api\Controller;

use GuzzleHttp\Client;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(
    defaults: [
        '_routeScope' => ['api'],
    ]
)]
class NostoCategoriesController extends AbstractController
{
    protected const APP_URL = 'https://api.nosto.com/v1/graphql';

    private EntityRepository $categoryRepository;

    private Client $client;

    private ConfigProvider $configProvider;

    public function __construct(
        EntityRepository $categoryRepository,
        ConfigProvider $configProvider,
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->configProvider = $configProvider;
        $this->client = new Client();
    }

    #[Route(
        path: "/api/_action/nosto-categories-controller/sync",
        name: "api.action.nosto-categories.sync",
        defaults: [
            'auth_required' => true,
        ],
        methods: ["POST"]
    )]
    public function sync(Request $request, Context $context): JsonResponse
    {
        $criteria = new Criteria();
        $criteria->addAssociation('children');
        $criteria->getAssociation('parent');
        $criteria->getAssociation('seoUrls');
        $criteria->addFilter(new EqualsFilter('active', true));

        $appToken = $this->configProvider->getAppToken(
            $request->request->get('salesChannelId'),
            $request->request->get('languageId'),
        );

        if (!$appToken) {
            return new JsonResponse(404);
        }

        $categories = $this->categoryRepository->search($criteria, $context)->getEntities();

        $categoryStructure = [];

        if (!$categories->count()) {
            return new JsonResponse();
        }

        foreach ($categories as $category) {
            if ($category->getActive()) {
                $data = [
                    'id' => $category->getId(),
                    'name' => $category->getName(),
                    'available' => $category->getActive(),
                ];

                if ($category->getSeoUrls()->getElements()) {
                    $firstSeoUrl = array_key_first($category->getSeoUrls()->getElements());
                    $url = $category->getSeoUrls()->getElements()[$firstSeoUrl]->getSeoPathInfo();
                    $data['urlPath'] = '/' . $url;
                    $data['fullName'] = '/' . $url;
                }

                if ($category->getParentId()) {
                    $data['parentId'] = $category->getParentId();
                }

                $categoryStructure[] = $data;
            }
        }

        $mutation = <<<EOF
            mutation {
              upsertCategories(categories: [
            EOF;

        foreach ($categoryStructure as $category) {
            $mutation .= <<<EOF
                            {
                                id: "{$category['id']}",
                                name: "{$category['name']}",
                                available: {$category['available']}
                        EOF;

            if (isset($category['urlPath']) && isset($category['fullName'])) {
                $mutation .= <<<EOF
                                    ,
                                    urlPath: "{$category['urlPath']}",
                                    fullName: "{$category['fullName']}"
                            EOF;
            }

            if (isset($category['parentId'])) {
                $mutation .= <<<EOF
                                ,
                                parentId: "{$category['parentId']}"
                        EOF;
            }

            $mutation .= <<<EOF
                    },
                EOF;
        }

        $mutation .= <<<EOF
              ]) {
                categoryResult {
                  errors {
                    field
                    message
                  }
                  category {
                    id
                    name
                    parentId
                    urlPath
                    fullName
                    available
                  }
                }
              }
            }
            EOF;

        $this->client->post(self::APP_URL, [
            'headers' => [
                'Content-Type' => 'application/graphql',
            ],
            'auth' => ['', $appToken],
            'body' => $mutation,
        ]);

        return new JsonResponse();
    }
}
