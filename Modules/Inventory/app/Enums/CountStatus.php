<?php

namespace Modules\Inventory\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum CountStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Counting = 'counting';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Counting => __('Counting'),
            self::Completed => __('Completed'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Counting => 'warning',
            self::Completed => 'success',
            self::Cancelled => 'secondary',
        };
    }
}
