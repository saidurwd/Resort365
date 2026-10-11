<?php

namespace Modules\Inventory\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * Which ledger accounts an item's stock belongs to (Step 5.4): set on a top-level item category and followed by its
 * subcategories. An issue posts Dr the class's cost account / Cr its inventory account.
 */
enum StockClass: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Food = 'food';
    case Beverage = 'beverage';
    case Housekeeping = 'housekeeping';
    case Maintenance = 'maintenance';
    case Operating = 'operating';

    public function label(): string
    {
        return match ($this) {
            self::Food => __('Food'),
            self::Beverage => __('Beverage'),
            self::Housekeeping => __('Housekeeping supplies'),
            self::Maintenance => __('Maintenance and engineering'),
            self::Operating => __('Operating supplies'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Food => 'warning',
            self::Beverage => 'info',
            self::Housekeeping => 'success',
            self::Maintenance => 'secondary',
            self::Operating => 'primary',
        };
    }
}
