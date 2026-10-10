<?php

namespace Modules\Accounting\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * A cheque on a voucher: pending until a bank statement line matches it (cleared), or bounced.
 */
enum ChequeStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Pending = 'pending';
    case Cleared = 'cleared';
    case Bounced = 'bounced';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Cleared => __('Cleared'),
            self::Bounced => __('Bounced'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Cleared => 'success',
            self::Bounced => 'danger',
        };
    }
}
