<?php

namespace Modules\Restaurant\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * A table's live state on the POS floor plan (used from Step 3.4).
 */
enum TableStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Available = 'available';
    case Occupied = 'occupied';
    case BillPrinted = 'bill_printed';
    case Reserved = 'reserved';
    case Cleaning = 'cleaning';

    public function label(): string
    {
        return match ($this) {
            self::Available => __('Available'),
            self::Occupied => __('Occupied'),
            self::BillPrinted => __('Bill printed'),
            self::Reserved => __('Reserved'),
            self::Cleaning => __('Needs cleaning'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::Occupied => 'danger',
            self::BillPrinted => 'warning',
            self::Reserved => 'info',
            self::Cleaning => 'secondary',
        };
    }
}
