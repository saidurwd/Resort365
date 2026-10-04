<?php

namespace Modules\Housekeeping\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * Out of order takes the room off sale (inventory locks); out of service only flags it on the board.
 */
enum BlockType: string implements HasLabelAndColor
{
    use EnumHelpers;

    case OutOfOrder = 'out_of_order';
    case OutOfService = 'out_of_service';

    public function label(): string
    {
        return match ($this) {
            self::OutOfOrder => __('Out of order'),
            self::OutOfService => __('Out of service'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::OutOfOrder => 'danger',
            self::OutOfService => 'warning',
        };
    }
}
