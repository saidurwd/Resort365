<?php

namespace Modules\Property\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * How a cottage can be sold (ARCHITECTURE §6.1): its rooms one by one, only as a whole, or both.
 */
enum BookingMode: string implements HasLabelAndColor
{
    use EnumHelpers;

    case RoomsOnly = 'rooms_only';
    case WholeOnly = 'whole_only';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::RoomsOnly => __('Rooms only'),
            self::WholeOnly => __('Whole only'),
            self::Both => __('Rooms or whole'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::RoomsOnly => 'info',
            self::WholeOnly => 'primary',
            self::Both => 'success',
        };
    }

    public function sellsRooms(): bool
    {
        return $this !== self::WholeOnly;
    }

    public function sellsWhole(): bool
    {
        return $this !== self::RoomsOnly;
    }
}
