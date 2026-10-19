<?php

namespace Modules\HR\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum HalfDay: string implements HasLabelAndColor
{
    use EnumHelpers;

    case First = 'first';
    case Second = 'second';

    public function label(): string
    {
        return match ($this) {
            self::First => __('First half'),
            self::Second => __('Second half'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::First => 'info',
            self::Second => 'info',
        };
    }
}
