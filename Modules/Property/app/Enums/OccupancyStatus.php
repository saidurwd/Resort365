<?php

namespace Modules\Property\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * Whether a guest is in the room. Shown read-only here; check-in and check-out change it (Phase 2).
 */
enum OccupancyStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Vacant = 'vacant';
    case Occupied = 'occupied';

    public function label(): string
    {
        return match ($this) {
            self::Vacant => __('Vacant'),
            self::Occupied => __('Occupied'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Vacant => 'secondary',
            self::Occupied => 'warning',
        };
    }
}
