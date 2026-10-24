<?php

namespace Modules\Website\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * What a guest booking on the website chose to pay now (Step 8.2b): the deposit, or the whole stay.
 */
enum PayChoice: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Deposit = 'deposit';
    case Full = 'full';

    public function label(): string
    {
        return match ($this) {
            self::Deposit => __('Pay the deposit now'),
            self::Full => __('Pay the full amount now'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Deposit => 'info',
            self::Full => 'success',
        };
    }
}
