<?php

namespace Modules\Housekeeping\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * A cleaning task: after a guest leaves, daily for a room in house, or a quick touch-up.
 */
enum TaskType: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Departure = 'departure';
    case Stayover = 'stayover';
    case TouchUp = 'touch_up';

    public function label(): string
    {
        return match ($this) {
            self::Departure => __('Departure clean'),
            self::Stayover => __('Stayover'),
            self::TouchUp => __('Touch-up'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Departure => 'danger',
            self::Stayover => 'info',
            self::TouchUp => 'secondary',
        };
    }
}
