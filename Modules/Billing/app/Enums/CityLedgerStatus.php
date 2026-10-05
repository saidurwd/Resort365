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
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::Paid => __('Paid'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::Paid => 'success',
            self::Cancelled => 'secondary',
        };
    }
}
