<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Storefront\Checkout\Cart\RestoreUrlService;

use Nosto\NostoIntegration\Entity\CheckoutMapping\CheckoutMappingDefinition;
use Nosto\NostoIntegration\Entity\CheckoutMapping\CheckoutMappingEntity;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class RestoreUrlService
{
    public function __construct(
        private readonly EntityRepository $mappingRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ConfigProvider $configProvider,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getCurrentRestoreUrl(SalesChannelContext $context): ?string
    {
        if ($this->configProvider->isEnabledStoreAbandonedCartData()) {
            $current = $this->fetchFromDb($context->getToken(), $context->getContext());
            return $this->generate(
                $current ? $current->getId() : $this->createNew($context->getToken(), $context->getContext()),
            );
        }

        return null;
    }

    protected function fetchFromDb(string $token, Context $context): ?CheckoutMappingEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('reference', $token),
            new EqualsFilter('mappingTable', CheckoutMappingDefinition::CART_TABLE),
        );
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        return $this->mappingRepository->search($criteria, $context)->first();
    }

    private function generate(string $id): string
    {
        // TODO: Change routing name
        return $this->requestStack->getCurrentRequest()->attributes->get(
            RequestTransformer::STOREFRONT_URL,
        ) . $this->urlGenerator->generate(
            'frontend.cart.nosto-restore-cart',
            [
                'mappingId' => $id,
            ],
            UrlGeneratorInterface::ABSOLUTE_PATH,
        );
    }

    protected function createNew(string $token, Context $context): string
    {
        $data = [
            'id' => Uuid::randomHex(),
            'reference' => $token,
            'mappingTable' => CheckoutMappingDefinition::CART_TABLE,
        ];
        $this->mappingRepository->create([$data], $context);
        return $data['id'];
    }
}
