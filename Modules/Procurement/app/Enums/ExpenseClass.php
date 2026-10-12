<?php

namespace Modules\Procurement\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum ExpenseClass: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Food = 'food';
    case Beverage = 'beverage';
    case Housekeeping = 'housekeeping';
    case Maintenance = 'maintenance';
    case Utilities = 'utilities';
    case Repairs = 'repairs';
    case Administrative = 'administrative';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Food => __('Food'),
            self::Beverage => __('Beverage'),
            self::Housekeeping => __('Housekeeping supplies'),
            self::Maintenance => __('Engineering supplies'),
            self::Utilities => __('Utilities'),
            self::Repairs => __('Repairs and maintenance'),
            self::Administrative => __('Office and administration'),
            self::Other => __('Other'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Food => 'success',
            self::Beverage => 'info',
            self::Housekeeping => 'primary',
            self::Maintenance => 'warning',
            self::Utilities => 'info',
            self::Repairs => 'warning',
            self::Administrative => 'secondary',
            self::Other => 'secondary',
        };
    }
}
