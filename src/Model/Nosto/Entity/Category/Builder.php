<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Entity\Category;

use Nosto\Model\Category\Category as NostoCategory;
use Nosto\NostoIntegration\Model\ConfigProvider;
use Nosto\NostoIntegration\Model\Nosto\Entity\Category\Event\NostoCategoryBuiltEvent;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class Builder
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SystemConfigService $systemConfigService,
        private readonly ConfigProvider $configProvider,
    ) {
    }

    public function build(
        CategoryEntity $category,
        SalesChannelContext $context,
    ): NostoCategory {
        $nostoCategory = new NostoCategory();
        $nostoCategory->setId($category->getId());
        $nostoCategory->setTitle($category->getTranslated()['name']);
        $nostoCategory->setAvailable($category->getActive());
        $nostoCategory->setParentId($category->getParentId());

        $domain = null;
        if ($domains = $context->getSalesChannel()->getDomains()) {
            $domainId = (string) $this->configProvider->getDomainId(
                $context->getSalesChannelId(),
                $context->getLanguageId(),
            );

            $domain = $domains->has($domainId) ? $domains->get($domainId) : $domains->first();
        }

        $url = '';
        if ($category->getSeoUrls()->getElements() && $domain) {
            foreach ($category->getSeoUrls()->getElements() as $seoUrl) {
                if ($seoUrl->getLanguageId() === $context->getLanguageId()
                    && $seoUrl->getSalesChannelId() === $context->getSalesChannelId()
                ) {
                    $url = $seoUrl->getSeoPathInfo();
                    $nostoCategory->setUrl($domain->getUrl() . '/' . $url);
                }
            }
        }

        if ($url !== '') {
            $nostoCategory->setPath('/' . $url);
            $nostoCategory->setCategoryString('/' . $url);
        } else {
            $nostoCategory->setPath('');
            $nostoCategory->setCategoryString('');
        }

        $this->eventDispatcher->dispatch(
            new NostoCategoryBuiltEvent($category, $nostoCategory, $context->getContext()),
        );
        return $nostoCategory;
    }
}
