<?php

namespace Modules\Billing\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * What a charge code is for; folio routing rules work by category.
 */
enum ChargeCategory: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Room = 'room';
    case FoodBeverage = 'food_beverage';
    case Extra = 'extra';
    case Service = 'service';
    case Miscellaneous = 'misc';

    public function label(): string
    {
        return match ($this) {
            self::Room => __('Room'),
            self::FoodBeverage => __('Food & beverage'),
            self::Extra => __('Extras'),
            self::Service => __('Services'),
            self::Miscellaneous => __('Miscellaneous'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Room => 'primary',
            self::FoodBeverage => 'warning',
            self::Extra => 'info',
            self::Service => 'success',
            self::Miscellaneous => 'secondary',
        };
    }
}
