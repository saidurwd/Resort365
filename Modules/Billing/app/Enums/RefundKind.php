<?php

namespace Modules\Billing\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * Why money is paid back: a cancelled booking (above the fee), a security deposit at check-out, a folio credit balance, or a credit note.
 */
enum RefundKind: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Cancellation = 'cancellation';
    case SecurityDeposit = 'security_deposit';
    case Overpayment = 'overpayment';
    case CreditNote = 'credit_note';

    public function label(): string
    {
        return match ($this) {
            self::Cancellation => __('Cancellation refund'),
            self::SecurityDeposit => __('Security deposit returned'),
            self::Overpayment => __('Overpayment refunded'),
            self::CreditNote => __('Credit note refund'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Cancellation => 'danger',
            self::SecurityDeposit => 'warning',
            self::Overpayment => 'info',
            self::CreditNote => 'secondary',
        };
    }
}
