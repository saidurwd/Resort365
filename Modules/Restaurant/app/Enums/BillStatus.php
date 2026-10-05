<?php

namespace Modules\Restaurant\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * A restaurant bill (ARCHITECTURE §5.10.7): printed (the pre-check, waiting for payment), settled
 * (paid; immutable), or voided (reopened before payment, or a manager's void on the same business date).
 */
enum BillStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Printed = 'printed';
    case Settled = 'settled';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Printed => __('Printed'),
            self::Settled => __('Settled'),
            self::Voided => __('Voided'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Printed => 'warning',
            self::Settled => 'success',
            self::Voided => 'danger',
        };
    }
}
