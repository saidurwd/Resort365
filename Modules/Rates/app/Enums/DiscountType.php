<?php

namespace Modules\Rates\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * How a promotion discounts a stay.
 */
enum DiscountType: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Percent = 'percent';
    case FixedPerNight = 'fixed_per_night';
    case FixedPerStay = 'fixed_per_stay';

    public function label(): string
    {
        return match ($this) {
            self::Percent => __('Percentage'),
            self::FixedPerNight => __('Amount per night'),
            self::FixedPerStay => __('Amount per stay'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Percent => 'success',
            self::FixedPerNight => 'info',
            self::FixedPerStay => 'primary',
        };
    }
}
