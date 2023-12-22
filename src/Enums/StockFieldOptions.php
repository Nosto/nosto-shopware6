<?php

declare(strict_types=1);

namespace Nosto\NostoIntegration\Enums;

enum StockFieldOptions: string
{
    case AVAILABLE_STOCK = 'available-stock';
    case ACTUAL_STOCK = 'actual-stock';
}
