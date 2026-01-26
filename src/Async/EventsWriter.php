<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Async;

use Nosto\NostoIntegration\Utils\NostoCriteriaFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class EventsWriter
{
    public const ORDER_ENTITY_PLACED_NAME = 'order_placed';

    public const ORDER_ENTITY_UPDATED_NAME = 'order_updated';

    public const NEWSLETTER_ENTITY_NAME = 'newsletter';

    public const PRODUCT_ENTITY_NAME = 'product';

    public const CATEGORY_ENTITY_NAME = 'category';

    public function __construct(
        private readonly EntityRepository $changelogRepository,
    ) {
    }

    public function writeEvent(string $name, string $id, Context $context, ?string $productNumber = null): void
    {
        $criteria = NostoCriteriaFactory::create('events_writer.find_existing_event')
            ->addFilter(new EqualsFilter('entityType', $name))
            ->addFilter(new EqualsFilter('entityId', $id))
            ->addFilter(new EqualsFilter('productNumber', $productNumber))
            ->setLimit(1);

        if ($this->changelogRepository->searchIds($criteria, $context)->firstId()) {
            return;
        }

        $this->changelogRepository->create([[
            'entityType' => $name,
            'entityId' => $id,
            'productNumber' => $productNumber,
        ]], $context);
    }
}
