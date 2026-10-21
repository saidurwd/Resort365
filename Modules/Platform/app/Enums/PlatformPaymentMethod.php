<?php

namespace Modules\Platform\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * How a tenant paid a platform invoice. Manual invoicing records them by hand (Step 7.4); a gateway records `card` itself later.
 */
enum PlatformPaymentMethod: string implements HasLabelAndColor
{
    use EnumHelpers;

    case BankTransfer = 'bank_transfer';
    case Card = 'card';
    case MobileWallet = 'mobile_wallet';
    case Cash = 'cash';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::BankTransfer => __('Bank transfer'),
            self::Card => __('Card'),
            self::MobileWallet => __('Mobile wallet'),
            self::Cash => __('Cash'),
            self::Other => __('Other'),
        };
    }

    public function color(): string
    {
        return 'secondary';
    }
}
