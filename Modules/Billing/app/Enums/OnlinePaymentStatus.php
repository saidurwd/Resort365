<?php

namespace Modules\Billing\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * Where an online payment attempt stands (Step 8.3). `unapplied`: the gateway took the money but the booking could no longer accept
 * it (cancelled or already paid meanwhile); staff refund it.
 */
enum OnlinePaymentStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Pending = 'pending';
    case Paid = 'paid';
    case Unapplied = 'unapplied';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Waiting for the gateway'),
            self::Paid => __('Paid'),
            self::Unapplied => __('Paid, not applied'),
            self::Failed => __('Failed'),
            self::Cancelled => __('Cancelled'),
            self::Expired => __('Expired'),
            self::Refunded => __('Refunded'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Paid => 'success',
            self::Unapplied => 'danger',
            self::Failed, self::Expired => 'secondary',
            self::Cancelled => 'secondary',
            self::Refunded => 'info',
        };
    }

    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }
}
