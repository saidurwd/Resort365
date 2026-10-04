<?php

namespace Modules\Billing\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * A company's open amount on the city ledger, until paid in full.
 */
enum CityLedgerStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Open = 'open';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::Paid => __('Paid'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::Paid => 'success',
        };
    }
}
