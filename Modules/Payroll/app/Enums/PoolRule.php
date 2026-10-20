<?php

namespace Modules\Payroll\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * How a pool is shared (Step 6.6): equally, by each person's points, or by the days they worked.
 */
enum PoolRule: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Equal = 'equal';
    case Points = 'points';
    case DaysWorked = 'days_worked';

    public function label(): string
    {
        return match ($this) {
            self::Equal => __('Equal shares'),
            self::Points => __('By points'),
            self::DaysWorked => __('By days worked'),
        };
    }

    public function color(): string
    {
        return 'secondary';
    }
}
