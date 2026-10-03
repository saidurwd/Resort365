<?php

namespace Modules\Billing\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * How a payment was made (ARCHITECTURE §5.9). Manual methods only; online gateways and city
 * ledger come with their steps.
 */
enum PaymentMethod: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Cash = 'cash';
    case Card = 'card';
    case BankTransfer = 'bank_transfer';
    case MobileWallet = 'mobile_wallet';

    public function label(): string
    {
        return match ($this) {
            self::Cash => __('Cash'),
            self::Card => __('Card'),
            self::BankTransfer => __('Bank transfer'),
            self::MobileWallet => __('Mobile wallet'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Cash => 'success',
            self::Card => 'primary',
            self::BankTransfer => 'info',
            self::MobileWallet => 'warning',
        };
    }

    /**
     * Whether the method usually has a reference (card slip, transaction id).
     */
    public function needsReference(): bool
    {
        return $this !== self::Cash;
    }
}
