<?php

namespace Modules\Rates\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * How a deposit policy asks for the advance (ARCHITECTURE §6.5).
 */
enum DepositType: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Percentage = 'percentage';
    case FixedAmount = 'fixed_amount';
    case FirstNight = 'first_night';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Percentage => __('Percentage of the total'),
            self::FixedAmount => __('Fixed amount'),
            self::FirstNight => __('First night'),
            self::None => __('No deposit'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Percentage => 'primary',
            self::FixedAmount => 'info',
            self::FirstNight => 'warning',
            self::None => 'secondary',
        };
    }
}
