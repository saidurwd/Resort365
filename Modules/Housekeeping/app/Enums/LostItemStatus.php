<?php

namespace Modules\Housekeeping\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * A found item is stored until it is claimed (returned) or disposed of.
 */
enum LostItemStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Stored = 'stored';
    case Claimed = 'claimed';
    case Disposed = 'disposed';

    public function label(): string
    {
        return match ($this) {
            self::Stored => __('Stored'),
            self::Claimed => __('Returned'),
            self::Disposed => __('Disposed'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Stored => 'warning',
            self::Claimed => 'success',
            self::Disposed => 'secondary',
        };
    }
}
