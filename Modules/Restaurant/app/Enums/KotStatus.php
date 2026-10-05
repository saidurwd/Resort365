<?php

namespace Modules\Restaurant\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * A kitchen ticket's state on the kitchen display (Step 3.5).
 */
enum KotStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case New = 'new';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::New => __('New'),
            self::Preparing => __('Preparing'),
            self::Ready => __('Ready'),
            self::Done => __('Done'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::Preparing => 'primary',
            self::Ready => 'success',
            self::Done => 'secondary',
        };
    }
}
