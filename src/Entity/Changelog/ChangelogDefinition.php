<?php

namespace Nosto\NostoIntegration\Entity\Changelog;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class ChangelogDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'nosto_integration_entity_changelog';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ChangelogEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ChangelogCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('entity_type', 'entityType'))->addFlags(new Required()),
            (new IdField('entity_id', 'entityId'))->addFlags(new Required()),
            new StringField('product_number', 'productNumber')
        ]);
    }
}