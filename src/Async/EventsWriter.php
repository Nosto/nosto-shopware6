<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Async;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

class EventsWriter
{
    private EntityRepository $changelogRepository;

    public const ORDER_ENTITY_PLACED_NAME = 'order_placed';

    public const ORDER_ENTITY_UPDATED_NAME = 'order_updated';

    public const NEWSLETTER_ENTITY_NAME = 'newsletter';

    public const PRODUCT_ENTITY_NAME = 'product';

    public function __construct(EntityRepository $changelogRepository)
    {
        $this->changelogRepository = $changelogRepository;
    }

    public function writeEvent(string $name, string $id, Context $context, ?string $productNumber = null): void
    {
        $this->changelogRepository->create([[
            'entityType' => $name,
            'entityId' => $id,
            'productNumber' => $productNumber,
        ]], $context);
    }
}
