<?php

namespace Nosto\NostoIntegration\Decorator\Core\Content\Product\DataAbstractionLayer;

use Shopware\Core\Content\Product\DataAbstractionLayer\VariantListingConfig as OriginalVariantListingConfig;

class VariantListingConfig extends OriginalVariantListingConfig
{
    public function __construct(
        ?bool $displayParent,
        ?string $mainVariantId,
        ?array $configuratorGroupConfig,
        protected ?bool $displayCheapestVariant,
        protected ?bool $displayMainVariant,
    ) {
        parent::__construct($displayParent, $mainVariantId, $configuratorGroupConfig);
    }

    public function getDisplayCheapestVariant(): ?bool
    {
        return $this->displayCheapestVariant;
    }

    public function getDisplayMainVariant(): ?bool
    {
        return $this->displayMainVariant;
    }
}