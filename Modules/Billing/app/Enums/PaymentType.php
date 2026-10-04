<?php

namespace Modules\Billing\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * What a payment is for: an advance before arrival, a payment during or after the stay, money
 * paid back (ARCHITECTURE §5.9), or a refundable security deposit held during the stay (not part
 * of what the booking has paid; returned at check-out).
 */
enum PaymentType: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Deposit = 'deposit';
    case Payment = 'payment';
    case Refund = 'refund';
    case SecurityDeposit = 'security_deposit';

    public function label(): string
    {
        return match ($this) {
            self::Deposit => __('Deposit'),
            self::Payment => __('Payment'),
            self::Refund => __('Refund'),
            self::SecurityDeposit => __('Security deposit'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Deposit => 'info',
            self::Payment => 'success',
            self::Refund => 'danger',
            self::SecurityDeposit => 'warning',
        };
    }
}
