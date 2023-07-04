<?php

declare(strict_types=1);

namespace Od\NostoIntegration\Api\Controller;

use GuzzleHttp\Exception\GuzzleException;
use Od\NostoIntegration\Utils\Lifecycle;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Composer\Repository\RepositoryInterface;
use Doctrine\DBAL\Connection;
use Od\NostoIntegration\Model\Nosto\Entity\Product\Event\CriteriaEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Content\Category\Service\AbstractCategoryUrlGenerator;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Od\NostoIntegration\Model\ConfigProvider;
use Od\NostoIntegration\Service\CategoryMerchandising\MerchandisingSearchApi;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use GuzzleHttp\Client;

class OdNostoCategoriesController extends AbstractController
{

    private EntityRepositoryInterface $categoryRepository;

    private AbstractCategoryUrlGenerator $categoryUrlGenerator;

    private $client;

    public function __construct(EntityRepositoryInterface $categoryRepository, AbstractCategoryUrlGenerator $categoryUrlGenerator)
    {
        $this->categoryRepository = $categoryRepository;
        $this->categoryUrlGenerator = $categoryUrlGenerator;
        $this->client = new Client();
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/_action/od-nosto-categories-controller/sync",
     *     name="api.action.od-nosto-categories.sync",
     *     methods={"POST"}
     * )
     * @param Request $request
     * @return JsonResponse
     * @throws GuzzleException
     */
    public function sync(Request $request, Context $context): JsonResponse
    {
        $criteria = new Criteria();
        $criteria->addAssociation('children');
        $criteria->getAssociation('parent');
        $criteria->getAssociation('seoUrls');
        $criteria->addFilter(new EqualsFilter(
            'active',
            true
        ));

        $categories = $this->categoryRepository->search($criteria, $context)->getEntities();
//         dd($categories);


        $categoryStructure = [];

        if (!$categories) {
            return new JsonResponse();
        }
//
        foreach ($categories as $category) {
            if ($category->getActive()) {
//                 dd($category);
                $data = [];
                $data['id'] = $category->getId();
                $data['name'] = $category->getName();
                $data['available'] = $category->getActive();

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

        $transformedCategories = [];

        foreach ($categoryStructure as $category) {
            $transformedCategory = [];

            if (isset($category["urlPath"]) && isset($category["fullName"])) {

                $transformedCategory["urlPath"] = $category["urlPath"];
                $transformedCategory = [
                    "id" => $category["id"],
                    "name" => $category["name"],
                    "fullName" => $category["fullName"],
                    "available" => $category["available"]
                ];
            }

            // Check if parent ID exists
            if (isset($category["parentId"])) {
                $transformedCategory["parentId"] = $category["parentId"];
            }

            $transformedCategories[] = $transformedCategory;
        }

        $transformedCategoriesJson = json_encode($transformedCategories);

        $mutation = <<<EOF
mutation {
  upsertCategories(categories: $transformedCategoriesJson) {
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

//        dd($mutation);


        $response = $this->client->post('https://api.nosto.com/v1/graphql', [
            'headers' => [
                'Content-Type' => 'application/graphql',
            ],
            'auth_basic' => ['', 'wYxEyZ0R5ASJWGUMARmUeoE9NADNvjSACDBuU0eyz2AtcBIOHyRe9q7yJTDRPxDh'],
            'body' => $mutation
        ]);


        $responseContent = $response->getContent();

        dd($responseContent);


        return new JsonResponse();
    }

    public function importSorting(Context $context)
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('key', MerchandisingSearchApi::MERCHANDISING_SORTING_KEY));
        $sorting = $this->categoryRepository->search($criteria, $context);
        if ($sorting->count() > 0) {
            return;
        }
        $this->sortingRepository->upsert([
            [
                'key' => MerchandisingSearchApi::MERCHANDISING_SORTING_KEY,
                'priority' => 0,
                'active' => true,
                'fields' => [],
                'label' => 'Recommendation',
                'locked' => false,
            ],
        ], $context);
    }

    public function getCategoryUrl(Context $context, CategoryEntity $category): ?string
    {
        $gago = new Context(new SystemSource());
        dd($gago);
        $salesChannel = null;

        return $this->categoryUrlGenerator->generate($category, $salesChannel);
    }
}