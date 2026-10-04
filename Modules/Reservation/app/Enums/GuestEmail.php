<?php

namespace Modules\Reservation\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * The emails a guest gets about a booking or a quote; each is an editable template (Setup → Email
 * templates) keyed by its value.
 */
enum GuestEmail: string implements HasLabelAndColor
{
    use EnumHelpers;

    case BookingCreated = 'reservation.booking_created';
    case BookingConfirmed = 'reservation.booking_confirmed';
    case BookingCancelled = 'reservation.booking_cancelled';
    case HoldExpired = 'reservation.hold_expired';
    case Quote = 'reservation.quote';

    public function label(): string
    {
        return match ($this) {
            self::BookingCreated => __('Booking received'),
            self::BookingConfirmed => __('Booking confirmed'),
            self::BookingCancelled => __('Booking cancelled'),
            self::HoldExpired => __('Booking released (deposit not paid)'),
            self::Quote => __('Quotation'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::BookingConfirmed => 'success',
            self::BookingCancelled, self::HoldExpired => 'danger',
            default => 'info',
        };
    }
}
