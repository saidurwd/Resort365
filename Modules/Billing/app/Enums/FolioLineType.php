<?php

namespace Modules\Billing\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * A folio line: a charge or adjustment adds to what is owed; a payment reduces it; a refund (money paid back) adds it back.
 */
enum FolioLineType: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Charge = 'charge';
    case Payment = 'payment';
    case Adjustment = 'adjustment';
    case Refund = 'refund';

    public function label(): string
    {
        return match ($this) {
            self::Charge => __('Charge'),
            self::Payment => __('Payment'),
            self::Adjustment => __('Adjustment'),
            self::Refund => __('Refund'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Charge => 'primary',
            self::Payment => 'success',
            self::Adjustment => 'warning',
            self::Refund => 'danger',
        };
    }

    /**
     * +1 when the line adds to what the guest owes, -1 when it reduces it (an adjustment's own
     * amount may be negative, e.g. a goodwill credit).
     */
    public function sign(): int
    {
        return $this === self::Payment ? -1 : 1;
    }
}
