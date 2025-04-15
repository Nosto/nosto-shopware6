<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Model\Nosto\Entity\Category\Event;

use Nosto\Model\Category\Category as NostoCategory;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class NostoCategoryBuiltEvent extends NestedEvent
{
    public function __construct(
        private readonly CategoryEntity $category,
        private readonly NostoCategory $nostoCategory,
        private readonly Context $context,
    ) {
    }

    public function getCategory(): CategoryEntity
    {
        return $this->category;
    }

    public function getNostoCategory(): NostoCategory
    {
        return $this->nostoCategory;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
