<?php

namespace Modules\Reservation\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * Why a room is taken on a night (inventory_locks.lock_type).
 */
enum LockType: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Reservation = 'reservation';
    case Hold = 'hold';
    case OutOfOrder = 'out_of_order';
    case OwnerBlock = 'owner_block';

    public function label(): string
    {
        return match ($this) {
            self::Reservation => __('Reserved'),
            self::Hold => __('On hold'),
            self::OutOfOrder => __('Out of order'),
            self::OwnerBlock => __('Owner block'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Reservation => 'primary',
            self::Hold => 'warning',
            self::OutOfOrder => 'danger',
            self::OwnerBlock => 'secondary',
        };
    }
}
