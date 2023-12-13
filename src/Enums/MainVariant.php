<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Enums;

enum MainVariant: string
{
    case SHOPWARE_DEFAULT = 'default';
    case MAIN_PARENT = 'parent';
    case CHEAPEST = 'cheapest';
}
