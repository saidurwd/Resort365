<?php

namespace Modules\Reservation\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * What happened to a reservation, as its history (reservation_logs) records it (ARCHITECTURE §6.7).
 */
enum ReservationLogAction: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Created = 'created';
    case Modified = 'modified';
    case DepositChanged = 'deposit_changed';
    case GuestAdded = 'guest_added';
    case GuestRemoved = 'guest_removed';
    case PrimaryGuestChanged = 'primary_guest_changed';
    case PaymentApplied = 'payment_applied';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case EmailSent = 'email_sent';
    case CheckedIn = 'checked_in';
    case CheckedOut = 'checked_out';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Created => __('Created'),
            self::Modified => __('Stay changed'),
            self::DepositChanged => __('Deposit changed'),
            self::GuestAdded => __('Guest added'),
            self::GuestRemoved => __('Guest removed'),
            self::PrimaryGuestChanged => __('Primary guest changed'),
            self::PaymentApplied => __('Payment received'),
            self::Confirmed => __('Confirmed'),
            self::Cancelled => __('Cancelled'),
            self::Expired => __('Hold expired'),
            self::EmailSent => __('Email'),
            self::CheckedIn => __('Checked in'),
            self::CheckedOut => __('Checked out'),
            self::NoShow => __('No-show'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Created, self::Confirmed => 'primary',
            self::PaymentApplied, self::CheckedIn => 'success',
            self::Cancelled, self::Expired, self::NoShow => 'danger',
            self::CheckedOut => 'secondary',
            self::DepositChanged => 'warning',
            self::EmailSent => 'info',
            default => 'secondary',
        };
    }
}
