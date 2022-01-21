<?php

namespace Od\NostoIntegration\Entity\Changelog;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class ChangelogEntity extends Entity
{
    use EntityIdTrait;

    protected string $entityType;
    protected string $entityId;

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function setEntityType(string $entityType): void
    {
        $this->entityType = $entityType;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function setEntityId(string $entityId): void
    {
        $this->entityId = $entityId;
    }

}