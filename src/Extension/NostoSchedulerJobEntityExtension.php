<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Extension;

use Nosto\Scheduler\Entity\Job\JobDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class NostoSchedulerJobEntityExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new JsonField('job_counts', 'jobCounts'))->addFlags(new ApiAware(), new Runtime()),
        );
    }

    public function getDefinitionClass(): string
    {
        return JobDefinition::class;
    }

    public function getEntityName(): string
    {
        return JobDefinition::ENTITY_NAME;
    }
}
