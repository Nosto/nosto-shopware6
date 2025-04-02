<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Entity\Category;

use Nosto\Model\Category\Category as NostoCategory;
use Nosto\NostoException;
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
        $nostoCategory->setTitle($category->getName());
        $nostoCategory->setAvailable($category->getActive());
        $nostoCategory->setParentId($category->getParentId());
        $nostoCategory->setPath($category->getPath() ?? '');

        if ($category->getSeoUrls()->getElements()) {
            $lastSeoUrl = array_key_last($category->getSeoUrls()->getElements());
            $url = $category->getSeoUrls()->getElements()[$lastSeoUrl]->getSeoPathInfo();
            $nostoCategory->setUrl('/' . $url);
            $nostoCategory->setCategoryString('/' . $url);
        }

        $this->eventDispatcher->dispatch(new NostoCategoryBuiltEvent($category, $nostoCategory, $context->getContext()));
        return $nostoCategory;
    }
}
