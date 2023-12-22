<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Enums;

enum RatingOptions: string
{
    case SHOPWARE_RATINGS = 'shopware-ratings';
    case NO_RATINGS = 'no-ratings';
}
