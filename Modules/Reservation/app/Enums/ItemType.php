<?php

namespace Modules\Reservation\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * What a reservation item books: one room, or a whole cottage (all its rooms) (ARCHITECTURE §6.1).
 */
enum ItemType: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Room = 'room';
    case Cottage = 'cottage';

    public function label(): string
    {
        return match ($this) {
            self::Room => __('Room'),
            self::Cottage => __('Whole cottage'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Room => 'info',
            self::Cottage => 'primary',
        };
    }
}
