<?php

namespace Modules\Restaurant\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * How an order is served (ARCHITECTURE §5.10.4). Room service, location delivery and staff meals come later.
 */
enum OrderType: string implements HasLabelAndColor
{
    use EnumHelpers;

    case DineIn = 'dine_in';
    case Takeaway = 'takeaway';

    public function label(): string
    {
        return match ($this) {
            self::DineIn => __('Dine-in'),
            self::Takeaway => __('Takeaway'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DineIn => 'primary',
            self::Takeaway => 'info',
        };
    }
}
