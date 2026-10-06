<?php

namespace Modules\Restaurant\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * A restaurant table reservation (ARCHITECTURE §5.10.12): booked → seated → completed, or cancelled / no-show.
 */
enum TableReservationStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Booked = 'booked';
    case Seated = 'seated';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Booked => __('Booked'),
            self::Seated => __('Seated'),
            self::Completed => __('Completed'),
            self::Cancelled => __('Cancelled'),
            self::NoShow => __('No-show'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Booked => 'info',
            self::Seated => 'primary',
            self::Completed => 'success',
            self::Cancelled => 'secondary',
            self::NoShow => 'danger',
        };
    }

    /**
     * Still holding its table.
     */
    public function isActive(): bool
    {
        return $this === self::Booked || $this === self::Seated;
    }
}
