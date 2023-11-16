<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Struct;

use Shopware\Core\Framework\Struct\Struct;

class Config extends Struct
{
    public ?bool $isEnabled = null;

    public ?string $accountID = null;

    public ?string $accountName = null;

    public ?string $productToken = null;

    public ?string $emailToken = null;

    public ?string $appToken = null;

    public ?string $searchToken = null;

    public ?bool $enableSearch = null;

    public ?bool $enableNavigation = null;

    public ?bool $isInitializeNostoAfterInteraction = null;

    public ?bool $enableMerch = null;

    public ?bool $notLoggedInCache = null;

    public ?string $domain = null;

    public ?array $selectedCustomFields = [];

    public ?array $tag1 = [];

    public ?array $tag2 = [];

    public ?array $tag3 = [];

    public ?string $googleCategory = null;

    public ?string $productIdentifier = null;

    public ?string $ratingsReviews = null;

    public ?string $stockField = null;

    public ?string $crossSellingSync = null;

    public ?string $categoryNaming = null;

    public ?bool $variations = null;

    public ?bool $productProperties = null;

    public ?bool $alternateImages = null;

    public ?bool $inventory = null;

    public ?bool $customerDataToNosto = null;

    public ?bool $syncInactiveProducts = null;

    public ?bool $productPublishedDateTagging = null;

    public ?bool $reloadRecommendations = null;

    public ?bool $enableLabelling = null;

    public ?bool $dailySynchronization = null;

    public mixed $dailySynchronizationTime = null;

    public function __construct(array $config)
    {
        foreach ($config as $key => $value) {
            $this->$key = $value;
        }
    }
}
