<?php

namespace Modules\Reservation\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * The reservation lifecycle (ARCHITECTURE §6.4).
 */
enum ReservationStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Tentative = 'tentative';
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case CheckedOut = 'checked_out';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Tentative => __('Tentative'),
            self::Confirmed => __('Confirmed'),
            self::CheckedIn => __('Checked in'),
            self::CheckedOut => __('Checked out'),
            self::Cancelled => __('Cancelled'),
            self::NoShow => __('No-show'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Tentative => 'warning',
            self::Confirmed => 'primary',
            self::CheckedIn => 'success',
            self::CheckedOut => 'secondary',
            self::Cancelled, self::NoShow => 'danger',
        };
    }

    /**
     * Whether the reservation still holds its rooms.
     */
    public function holdsRooms(): bool
    {
        return in_array($this, [self::Tentative, self::Confirmed, self::CheckedIn], true);
    }
}
