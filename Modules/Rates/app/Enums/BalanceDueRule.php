<?php

namespace Modules\Rates\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * When the rest of the stay (after the deposit) is due.
 */
enum BalanceDueRule: string implements HasLabelAndColor
{
    use EnumHelpers;

    case AtCheckIn = 'at_check_in';
    case DaysBeforeArrival = 'days_before_arrival';

    public function label(): string
    {
        return match ($this) {
            self::AtCheckIn => __('At check-in'),
            self::DaysBeforeArrival => __('Days before arrival'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::AtCheckIn => 'secondary',
            self::DaysBeforeArrival => 'info',
        };
    }
}
