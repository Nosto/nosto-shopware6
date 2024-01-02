<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Enums;

enum CrossSellingSyncOptions: string
{
    case NO_SYNC = 'no-sync';
    case ONLY_ACTIVE = 'only-active-sync';
    case ALL = 'all-sync';
}
