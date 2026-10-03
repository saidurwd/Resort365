<?php

namespace Modules\Reservation\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * How much of a reservation is paid (ARCHITECTURE §6.4), tracked apart from its status.
 */
enum PaymentStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Unpaid = 'unpaid';
    case DepositPaid = 'deposit_paid';
    case FullyPaid = 'fully_paid';
    case Overpaid = 'overpaid';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => __('Unpaid'),
            self::DepositPaid => __('Deposit paid'),
            self::FullyPaid => __('Fully paid'),
            self::Overpaid => __('Overpaid'),
            self::Refunded => __('Refunded'),
            self::PartiallyRefunded => __('Partially refunded'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Unpaid => 'danger',
            self::DepositPaid => 'warning',
            self::FullyPaid => 'success',
            self::Overpaid => 'info',
            self::Refunded, self::PartiallyRefunded => 'secondary',
        };
    }
}
