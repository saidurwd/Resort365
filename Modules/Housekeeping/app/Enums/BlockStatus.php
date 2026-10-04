<?php

namespace Modules\Housekeeping\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * A block is active until it is ended (early or on its last day).
 */
enum BlockStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Active = 'active';
    case Ended = 'ended';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Ended => __('Ended'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'danger',
            self::Ended => 'secondary',
        };
    }
}
