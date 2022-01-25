<?php

namespace Od\NostoIntegration\Async;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

class EventsWriter
{
    private EntityRepositoryInterface $changelogRepository;
    public const ORDER_ENTITY_NAME = 'order';
    public const NEWSLETTER_ENTITY_NAME = 'newsletter';

    public function __construct(EntityRepositoryInterface $changelogRepository)
    {
        $this->changelogRepository = $changelogRepository;
    }

    public function writeEvent(string $name, string $id, Context $context)
    {
        $this->changelogRepository->create([[
            'entityType' => $name,
            'entityId' => $id,
        ]], $context);
    }
}