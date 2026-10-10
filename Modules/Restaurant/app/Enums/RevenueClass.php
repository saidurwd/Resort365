<?php

namespace Modules\Restaurant\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * Whether a menu category's sales are food or beverage revenue in the ledger (Step 4.3).
 */
enum RevenueClass: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Food = 'food';
    case Beverage = 'beverage';

    public function label(): string
    {
        return match ($this) {
            self::Food => __('Food'),
            self::Beverage => __('Beverage'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Food => 'success',
            self::Beverage => 'info',
        };
    }
}
