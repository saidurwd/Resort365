<?php

namespace Modules\Billing\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * What an online payment pays (Step 8.3): the deposit, the whole stay, or the balance (payment links).
 */
enum OnlinePaymentPurpose: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Deposit = 'deposit';
    case Full = 'full';
    case Balance = 'balance';

    public function label(): string
    {
        return match ($this) {
            self::Deposit => __('Deposit'),
            self::Full => __('Full amount'),
            self::Balance => __('Balance'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Deposit => 'info',
            self::Full => 'success',
            self::Balance => 'primary',
        };
    }
}
