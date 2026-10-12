<?php

namespace Modules\Restaurant\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum WastageReason: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Spoiled = 'spoiled';
    case Expired = 'expired';
    case Dropped = 'dropped';
    case Overproduction = 'overproduction';
    case Returned = 'returned';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Spoiled => __('Spoiled'),
            self::Expired => __('Expired'),
            self::Dropped => __('Dropped or spilled'),
            self::Overproduction => __('Overproduction'),
            self::Returned => __('Returned by a guest'),
            self::Other => __('Other'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Spoiled => 'danger',
            self::Expired => 'warning',
            self::Dropped => 'warning',
            self::Overproduction => 'info',
            self::Returned => 'secondary',
            self::Other => 'secondary',
        };
    }
}
