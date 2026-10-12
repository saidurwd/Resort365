<?php

namespace Modules\Procurement\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum VendorPaymentMethod: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case MobileWallet = 'mobile_wallet';
    case Cheque = 'cheque';

    public function label(): string
    {
        return match ($this) {
            self::Cash => __('Cash'),
            self::BankTransfer => __('Bank transfer'),
            self::MobileWallet => __('Mobile wallet'),
            self::Cheque => __('Cheque'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Cash => 'secondary',
            self::BankTransfer => 'primary',
            self::MobileWallet => 'info',
            self::Cheque => 'warning',
        };
    }
}
