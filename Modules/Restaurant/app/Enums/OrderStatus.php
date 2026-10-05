<?php

namespace Modules\Restaurant\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * An order's state (ARCHITECTURE §5.10.5): open while being taken; bill printed, settled and voided come with bills (Step 3.6); cancelled when nothing reached the kitchen.
 */
enum OrderStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Open = 'open';
    case BillPrinted = 'bill_printed';
    case Settled = 'settled';
    case Cancelled = 'cancelled';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::BillPrinted => __('Bill printed'),
            self::Settled => __('Settled'),
            self::Cancelled => __('Cancelled'),
            self::Voided => __('Voided'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'success',
            self::BillPrinted => 'warning',
            self::Settled => 'secondary',
            self::Cancelled => 'danger',
            self::Voided => 'danger',
        };
    }
}
