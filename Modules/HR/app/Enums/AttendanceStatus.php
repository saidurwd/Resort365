<?php

namespace Modules\HR\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum AttendanceStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Present = 'present';
    case Late = 'late';
    case HalfDay = 'half_day';
    case Absent = 'absent';
    case Leave = 'leave';
    case Holiday = 'holiday';
    case DayOff = 'day_off';
    case Incomplete = 'incomplete';

    public function label(): string
    {
        return match ($this) {
            self::Present => __('Present'),
            self::Late => __('Late'),
            self::HalfDay => __('Half day'),
            self::Absent => __('Absent'),
            self::Leave => __('On leave'),
            self::Holiday => __('Holiday'),
            self::DayOff => __('Day off'),
            self::Incomplete => __('Missing punch'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Present => 'success',
            self::Late => 'warning',
            self::HalfDay => 'info',
            self::Absent => 'danger',
            self::Leave => 'info',
            self::Holiday => 'primary',
            self::DayOff => 'secondary',
            self::Incomplete => 'warning',
        };
    }
}
